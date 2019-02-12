<?php

declare(strict_types = 1);

namespace Drupal\oe_epoetry\Controller;

use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\Psr7\Response;
use Http\Mock\Client;
use OpenEuropa\EPoetry\EPoetryClientFactory;
use OpenEuropa\EPoetry\Type\CreateRequests;
use OpenEuropa\EPoetry\Type\LinguisticRequestIn;
use OpenEuropa\EPoetry\Type\RequestGeneralInfoIn;
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
  public function __construct(EPoetryClientFactory $epoetry, Client $httpClient) {
    $this->epoetry = $epoetry;
    $this->httpClient = $httpClient;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_epoetry.epoetry_client'),
      $container->get('oe_epoetry.http_mock')
    );
  }

  /**
   * Run test.
   */
  public function test() {

    // Generate response.
    $content = file_get_contents(__DIR__ . '/../../vendor/openeuropa/epoetry-client/tests/fixtures/create-requests-response.xml');
    $response = new Response(200, [], $content);
    $this->httpClient->addResponse($response);

    $clientFactory = $this->epoetry;
    $client = $clientFactory->getClient();

    // Generate request.
    $generalInfo = new RequestGeneralInfoIn();
    $generalInfo->setTitle('Test');
    $linguisticRequestIn = new LinguisticRequestIn();
    $linguisticRequestIn->setGeneralInfo($generalInfo);
    $createRequests = new CreateRequests();
    $createRequests->setLinguisticRequest($linguisticRequestIn);

    $client->createRequests($createRequests);

    $request = $client->debugLastSoapRequest()['request'];

    $build = [
      '#markup' => var_export($request['headers'], TRUE),
    ];
    return $build;
  }

}
