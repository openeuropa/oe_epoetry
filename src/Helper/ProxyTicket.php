<?php

declare(strict_types = 1);

namespace Drupal\oe_epoetry\Helper;

use Drupal\cas\Service\CasProxyHelper;
use OpenEuropa\EPoetry\Middleware\CasProxyTicketInterface;

/**
 * Class ProxyTicket.
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
   * The URL of the service to be proxied.
   *
   * @var string
   */
  private $targetService;

  /**
   * ProxyTicket constructor.
   *
   * @param \Drupal\cas\Service\CasProxyHelper $cas_proxy_helper
   *   The CAS Proxy Helper service
   * @param string $targetService
   *   The ePoetry service URL.
   */
  public function __construct(CasProxyHelper $cas_proxy_helper, string $targetService) {
    $this->casProxyHelper = $cas_proxy_helper;
    $this->targetService = $targetService;
  }

  /**
   * Get Proxy Ticket from CAS service.
   *
   * @return string
   *   The Proxy Ticket.
   *
   * @throws \Drupal\cas\Exception\CasProxyException
   *   Thrown if there was a problem communicating with the CAS server.
   */
  public function getProxyTicket(): string {
    return $this->casProxyHelper->getProxyTicket($this->targetService);
  }

}
