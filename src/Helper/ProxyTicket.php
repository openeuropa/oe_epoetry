<?php

declare(strict_types = 1);

namespace Drupal\oe_epoetry\Helper;

use Drupal\cas\Exception\CasProxyException;
use Drupal\cas\Service\CasHelper;
use Drupal\cas\Service\CasProxyHelper;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use OpenEuropa\EPoetry\Middleware\CasProxyTicketInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class ProxyTicket
 *
 * @package Drupal\oe_epoetry\Helper
 */
class ProxyTicket implements CasProxyTicketInterface {

  /**
   * CAS Proxy Helper object.
   *
   * @var \Drupal\cas\Service\CasProxyHelper
   */
  protected $casProxyHelper;

  /**
   * The Guzzle HTTP client used to make ticket validation request.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * CAS Helper object.
   *
   * @var \Drupal\cas\Service\CasHelper
   */
  protected $casHelper;

  /**
   * Used to get session data.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected $session;

  /**
   * Stores settings object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $settings;

  /**
   * The URL of the service to be proxied.
   *
   * @var string
   *
   * @todo Move this setting into module customizable settings.
   */
  private $target_service = 'http://localhost:7001/epoetry/webservices/dgtService';

  /**
   * ProxyTicket constructor.
   *
   * @param \Drupal\cas\Service\CasProxyHelper $cas_proxy_helper
   *   The CAS Proxy Helper service.
   * @param \GuzzleHttp\Client $http_client
   *   The HTTP Client library.
   * @param \Drupal\cas\Service\CasHelper $cas_helper
   *   The CAS Helper service.
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(CasProxyHelper $cas_proxy_helper, Client $http_client, CasHelper $cas_helper, SessionInterface $session, ConfigFactoryInterface $config_factory) {
    $this->casProxyHelper = $cas_proxy_helper;
    $this->httpClient = $http_client;
    $this->casHelper = $cas_helper;
    $this->session = $session;
    $this->settings = $config_factory->get('cas.settings');
  }

  /**
   * @inheritDoc
   */
  public function getProxyTicket(): string {
    return $this->proxyAuthenticate($this->target_service);
  }

  /**
   * Proxy authenticates to a target service and get
   * a Proxy Ticket.
   *
   * @param string $target_service
   *   The service to be proxied.
   *
   * @return string
   *   The Proxy Ticket.
   *
   * @throws CasProxyException
   *   Thrown if there was a problem communicating with the CAS server.
   *
   * @todo Use \Drupal\cas\Service\CasProxyHelper::proxyAuthenticate after
   * contrib module is patched for method to return the Proxy Ticket.
   */
  public function proxyAuthenticate($target_service) {
    $cas_proxy_helper = $this->session->get('cas_proxy_helper');
    // Check to see if we have proxied this application already.
    if (isset($cas_proxy_helper[$target_service])) {
      $cookies = array();
      foreach ($cas_proxy_helper[$target_service] as $cookie) {
        $cookies[$cookie['Name']] = $cookie['Value'];
      }
      $domain = $cookie['Domain'];
      $jar = CookieJar::fromArray($cookies, $domain);
      $this->casHelper->log(LogLevel::DEBUG, "%target_service already proxied. Returning information from session.", array('%target_service' => $target_service));
      return $jar;
    }

    if (!($this->settings->get('proxy.initialize') && $this->session->has('cas_pgt'))) {
      // We can't perform proxy authentication in this state.
      throw new CasProxyException("Session state not sufficient for proxying.");
    }

    // Make request to CAS server to retrieve a proxy ticket for this service.
    $cas_url = $this->getServerProxyUrl($target_service);
    try {
      $this->casHelper->log(LogLevel::DEBUG, "Retrieving proxy ticket from %cas_url", array('%cas_url' => $cas_url));
      $response = $this->httpClient->get($cas_url, [
        'timeout' => $this->settings->get('advanced.connection_timeout'),
        'verify' => FALSE
      ]);
    }
    catch (ClientException $e) {
      throw new CasProxyException($e->getMessage());
    }
    $proxy_ticket = $this->parseProxyTicket($response->getBody()->getContents());
    $this->casHelper->log(LogLevel::DEBUG, "Extracted proxy ticket %ticket", array('%ticket' => $proxy_ticket));

    return $proxy_ticket;
  }

  /**
   * Format a CAS Server proxy ticket request URL.
   *
   * @param string $target_service
   *   The service to be proxied.
   *
   * @return string
   *   The fully formatted URL.
   *
   * @todo Remove together with proxyAuthenticate method.
   */
  private function getServerProxyUrl($target_service) {
    $url = $this->casHelper->getServerBaseUrl() . 'proxy';
    $params = array();
    $params['pgt'] = $this->session->get('cas_pgt');
    $params['targetService'] = $target_service;
    return $url . '?' . UrlHelper::buildQuery($params);
  }

  /**
   * Parse proxy ticket from CAS Server response.
   *
   * @param string $xml
   *   XML response from CAS Server.
   *
   * @return mixed
   *   A proxy ticket to be used with the target service, FALSE on failure.
   *
   * @throws CasProxyException
   *   Thrown if there was a problem parsing the proxy validation response.
   *
   * @todo Remove together with proxyAuthenticate method.
   */
  private function parseProxyTicket($xml) {
    $dom = new \DomDocument();
    $dom->preserveWhiteSpace = FALSE;
    $dom->encoding = "utf-8";
    if (@$dom->loadXML($xml) === FALSE) {
      throw new CasProxyException("CAS Server returned non-XML response.");
    }
    $failure_elements = $dom->getElementsByTagName("proxyFailure");
    if ($failure_elements->length > 0) {
      // Something went wrong with proxy ticket validation.
      throw new CasProxyException("CAS Server rejected proxy request.");
    }
    $success_elements = $dom->getElementsByTagName("proxySuccess");
    if ($success_elements->length === 0) {
      // Malformed response from CAS Server.
      throw new CasProxyException("CAS Server returned malformed response.");
    }
    $success_element = $success_elements->item(0);
    $proxy_ticket = $success_element->getElementsByTagName("proxyTicket");
    if ($proxy_ticket->length === 0) {
      // Malformed ticket.
      throw new CasProxyException("CAS Server provided invalid or malformed ticket.");
    }
    return $proxy_ticket->item(0)->nodeValue;
  }
}
