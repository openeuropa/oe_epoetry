<?php

declare(strict_types = 1);

namespace Drupal\oe_epoetry\Helper;

use Drupal\cas\Exception\CasProxyException;
use Drupal\cas\Service\CasProxyHelper;
use Drupal\Component\Utility\UrlHelper;
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
   * The Guzzle HTTP client used to make ticket validation request.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Used to get session data.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected $session;

  /**
   * ProxyTicket constructor.
   *
   * @param \GuzzleHttp\Client $http_client
   *   The HTTP Client library.
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session manager.
   */
  public function __construct(Client $http_client, SessionInterface $session) {
    $this->session = $session;
    $this->httpClient = $http_client;
  }

  /**
   * @inheritDoc
   */
  public function getProxyTicket(): string {

    /** @var $pthelper \Drupal\cas\Service\CasProxyHelper **/
    $proxy_helper = \Drupal::service('cas.proxy_helper');

    $pt = $proxy_helper->proxyAuthenticate('http://localhost:7001/epoetry/webservices/dgtService');

    return $pt;

  }

  /**
   * Proxy authenticates to a target service.
   *
   * Returns cookies from the proxied service in a
   * CookieJar object for use when later accessing resources.
   *
   * @param string $target_service
   *   The service to be proxied.
   *
   * @return \GuzzleHttp\Cookie\CookieJar
   *   A CookieJar object (array storage) containing cookies from the
   *   proxied service.
   *
   * @throws CasProxyException
   *   Thrown if there was a problem communicating with the CAS server
   *   or if there was is invalid use rsession data.
   */
  public function proxyAuthenticate($target_service) {
    /** @var CasProxyHelper $cas_proxy_helper */
    $cas_proxy_helper = $this->session->get('cas_proxy_helper');
    // Check to see if we have proxied this application already.
    if (isset($cas_proxy_helper[$target_service])) {
      $cookies = array();
      foreach ($cas_proxy_helper[$target_service] as $cookie) {
        $cookies[$cookie['Name']] = $cookie['Value'];
      }
      $domain = $cookie['Domain'];
      $jar = CookieJar::fromArray($cookies, $domain);
      //$this->casHelper->log(LogLevel::DEBUG, "%target_service already proxied. Returning information from session.", array('%target_service' => $target_service));
      return $jar;
    }

    // Make request to CAS server to retrieve a proxy ticket for this service.
    $cas_url = $this->getServerProxyUrl($target_service);
    try {
      //$this->casHelper->log(LogLevel::DEBUG, "Retrieving proxy ticket from %cas_url", array('%cas_url' => $cas_url));

      $httpClientClass = get_class($this->httpClient);
      $httpClient = new $httpClientClass;
      //['timeout' => $this->settings->get('advanced.connection_timeout'), 'verify' => FALSE]
      $response = $httpClient->get($cas_url, ['timeout' => '10000']);
    }
    catch (ClientException $e) {
      throw new CasProxyException($e->getMessage());
    }
    $proxy_ticket = $this->parseProxyTicket($response->getBody());
    // $this->casHelper->log(LogLevel::DEBUG, "Extracted proxy ticket %ticket", array('%ticket' => $proxy_ticket));

    return $proxy_ticket;

    // Make request to target service with our new proxy ticket.
    // The target service will validate this ticket against the CAS server
    // and set a cookie that grants authentication for further resource calls.
    $params['ticket'] = $proxy_ticket;
    $service_url = $target_service . "?" . UrlHelper::buildQuery($params);
    $cookie_jar = new CookieJar();
    try {
      $this->casHelper->log(LogLevel::DEBUG, "Contacting service: %service", array('%service' => $service_url));
      $this->httpClient->get($service_url, ['cookies' => $cookie_jar, 'timeout' => $this->settings->get('advanced.connection_timeout')]);
    }
    catch (ClientException $e) {
      throw new CasProxyException($e->getMessage());
    }
    // Store in session storage for later reuse.
    $cas_proxy_helper[$target_service] = $cookie_jar->toArray();
    $this->session->set('cas_proxy_helper', $cas_proxy_helper);
    $this->casHelper->log(LogLevel::DEBUG, "Stored cookies from %service in session.", array('%service' => $target_service));
    return $cookie_jar;
  }

  /**
   * Format a CAS Server proxy ticket request URL.
   *
   * @param string $target_service
   *   The service to be proxied.
   *
   * @return string
   *   The fully formatted URL.
   */
  private function getServerProxyUrl($target_service) {
    $url = 'https://authentication:7002/cas/proxy';
    $params = array();
    $params['pgt'] = $this->session->get('cas_pgt');
    $params['targetService'] = $target_service;
    return $url . '?' . UrlHelper::buildQuery($params);
  }
}
