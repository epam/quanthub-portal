<?php

namespace Drupal\quanthub_core;

use GuzzleHttp\Client;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxy;
use GuzzleHttp\Exception\ConnectException;

/**
 * Layer before PHP SOAP client for getting allowed content from wso2.
 */
class XacmlSoapClient {

  /**
   * Data Type.
   *
   * @var string
   */
  const DATA_TYPE = 'http://www.w3.org/2001/XMLSchema#string';

  /**
   * Url for request to wso2.
   *
   * @var string
   */
  protected $route;

  /**
   * Guzzle service definition.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The user info service.
   *
   * @var \Drupal\quanthub_core\UserInfo
   */
  protected $userInfo;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * The Wso Token service.
   *
   * @var \Drupal\quanthub_core\WsoToken
   */
  protected $wsoToken;

  /**
   * Constructor.
   */
  public function __construct(Client $http_client, LoggerChannelFactoryInterface $logger_factory, UserInfo $user_info, AccountProxy $current_user, WsoToken $wso_token) {
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory;
    $this->userInfo = $user_info;
    $this->currentUser = $current_user;
    $this->wsoToken = $wso_token;

    $this->route = getenv('XACML_HOST');
  }

  /**
   * Send request to wso2 and get list of datasets from response.
   */
  public function getDatasetList() {
    $xml = $this->prepareXml();

    // Credentials for authentication by wso Token.
    $auth = "Bearer {$this->wsoToken->getToken()}";

    try {
      $response = $this->httpClient->post($this->route,
        [
          'verify' => FALSE,
          'body' => $xml,
          'headers' => [
            'Authorization' => $auth,
            'Content-Type' => 'application/xml',
          ],
        ]
      );
    }
    catch (ConnectException $e) {
      $this->loggerFactory->get('quanthub_core')->notice('Problem with getting Dataset list');
    }

    // Get dataset IDs from response from wso2.
    $dataset_list = [];
    if ($response->getStatusCode() == 200) {
      // @todo need to check if xml or jsonlogic response.
      $response_xml = new \SimpleXMLElement((string) $response->getBody());
      $key = 0;
      if (!empty($response_xml->Result->Obligations->Obligation)) {
        foreach ($response_xml->Result->Obligations->Obligation as $obligation) {
          if ($obligation->count() == 1) {
            $dataset_list[$key] = (string) $obligation->AttributeAssignment;
          }
          elseif ($obligation->count() > 1) {
            foreach ($obligation as $obligation_value) {
              $dataset_list[$key][(string) $obligation_value->attributes()] = (string) $obligation_value;
            }
          }

          $dataset_list[$key]['value'] = $dataset_list[$key]['Quanthub:Entity:Agency'] . ':' . $dataset_list[$key]['Quanthub:Entity:Code'];
          unset(
            $dataset_list[$key]['Quanthub:Entity:Agency'],
            $dataset_list[$key]['Quanthub:Entity:Code']
          );
          if (!empty($dataset_list[$key]['Quanthub:Entity:Version'])) {
            $dataset_list[$key]['value'] .= '(' . $dataset_list[$key]['Quanthub:Entity:Version'] . ')';
            unset($dataset_list[$key]['Quanthub:Entity:Version']);
          }
          $dataset_list[$key] = $dataset_list[$key]['value'];
          $key++;
        }
      }
      // @todo need to write jsonLogic handler.
      // JsonLogic::apply();
    }

    return $dataset_list;
  }

  /**
   * Prepare XML for request to wso2.
   */
  public function prepareXml() {
    $xml = new \SimpleXMLElement('<Request xmlns="urn:oasis:names:tc:xacml:3.0:core:schema:wd-17" CombinedDecision="false" ReturnPolicyIdList="false"></Request>');

    $attributes = $xml->addChild('Attributes');
    $attributes->addAttribute('Category', 'Quanthub:Action');

    $attribute = $attributes->addChild('Attribute');
    $attribute->addAttribute('AttributeId', 'Quanthub:Action:Type');
    $attribute->addAttribute('IncludeInResult', 'false');

    $attributeValue = $attribute->addChild('AttributeValue', 'Dataset.List');
    $attributeValue->addAttribute('DataType', $this::DATA_TYPE);

    $attributes = $xml->addChild('Attributes');
    $attributes->addAttribute('Category', 'Quanthub:Entities');

    $attribute = $attributes->addChild('Attribute');
    $attribute->addAttribute('AttributeId', 'Quanthub:Workspace:Access');
    $attribute->addAttribute('IncludeInResult', 'false');
    $attributeValue = $attribute->addChild('AttributeValue', 'RESTRICTED');
    $attributeValue->addAttribute('DataType', $this::DATA_TYPE);

    $attribute = $attributes->addChild('Attribute');
    $attribute->addAttribute('AttributeId', 'Quanthub:Workspace:Name');
    $attribute->addAttribute('IncludeInResult', 'false');
    $attributeValue = $attribute->addChild('AttributeValue', 'go');
    $attributeValue->addAttribute('DataType', $this::DATA_TYPE);

    $attribute = $attributes->addChild('Attribute');
    $attribute->addAttribute('AttributeId', 'Quanthub:Entitlement:Environment');
    $attribute->addAttribute('IncludeInResult', 'false');
    $attributeValue = $attribute->addChild('AttributeValue', 'PORTALS');
    $attributeValue->addAttribute('DataType', $this::DATA_TYPE);

    $attributes = $xml->addChild('Attributes');
    $attributes->addAttribute('Category', 'Quanthub:User');

    $attribute = $attributes->addChild('Attribute');
    $attribute->addAttribute('AttributeId', 'Quanthub:User:Role');
    $attribute->addAttribute('IncludeInResult', 'false');

    $roles = $this->userInfo->getUserInfoRole();
    foreach ($roles as $role) {
      $attributeValue = $attribute->addChild('AttributeValue', $role);
      $attributeValue->addAttribute('DataType', $this::DATA_TYPE);
    }

    if ($this->currentUser->isAuthenticated()) {
      $attribute = $attributes->addChild('Attribute');
      $attribute->addAttribute('AttributeId', 'Quanthub:User:UserID');
      $attribute->addAttribute('IncludeInResult', 'false');
      $attributeValue = $attribute->addChild('AttributeValue', $this->userInfo->getQuanthubUserId());
      $attributeValue->addAttribute('DataType', $this::DATA_TYPE);

      $attribute = $attributes->addChild('Attribute');
      $attribute->addAttribute('AttributeId', 'Quanthub:User:Groups');
      $attribute->addAttribute('IncludeInResult', 'false');
      $groups = $this->userInfo->getUserInfoGroups();
      foreach ($groups as $group) {
        $attributeValue = $attribute->addChild('AttributeValue', $group);
        $attributeValue->addAttribute('DataType', $this::DATA_TYPE);
      }
    }

    return $xml->asXML();
  }

}
