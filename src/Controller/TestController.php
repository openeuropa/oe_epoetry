<?php

declare(strict_types = 1);

namespace Drupal\oe_epoetry\Controller;

use Drupal\Core\Controller\ControllerBase;
use Http\Adapter\Guzzle6\Client;
use OpenEuropa\EPoetry\ClientFactory;
use OpenEuropa\EPoetry\Request\Type\CreateRequests;
use OpenEuropa\EPoetry\Request\Type\LinguisticRequestIn;
use OpenEuropa\EPoetry\Request\Type\RequestGeneralInfoIn;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Test epoetry.
 */
class TestController extends ControllerBase {
  protected $epoetry;

  protected $httpClient;

  /**
   * TestController construct method.
   */
  public function __construct(ClientFactory $epoetry, Client $httpClient) {
    $this->epoetry = $epoetry;
    $this->httpClient = $httpClient;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_epoetry.epoetry_client'),
      $container->get('oe_epoetry.http_client_adapter')
    );
  }

  /**
   * Run test.
   */
  public function test() {

    $clientFactory = $this->epoetry;
    $client = $clientFactory->getRequestClient();

    // Generate request.
    $generalInfo = new RequestGeneralInfoIn();
    $generalInfo->setTitle('Test');
    $linguisticRequestIn = new LinguisticRequestIn();
    $linguisticRequestIn->setGeneralInfo($generalInfo);
    $createRequests = new CreateRequests();
    $createRequests->setLinguisticRequest([$linguisticRequestIn]);

    $client->createRequests($createRequests);
    $request = $client->debugLastSoapRequest()['request'];

    $build = [
      '#markup' => var_export($request['headers'], TRUE),
    ];
    return $build;
  }

}
