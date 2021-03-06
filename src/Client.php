<?php

namespace Drip;

use Drip\Exception\DripException;
use Drip\Exception\InvalidArgumentException;
use Drip\Exception\InvalidApiTokenException;
use Drip\Exception\InvalidAccountIdException;
use Drip\Exception\UnexpectedHttpVerbException;

/**
 * Drip API
 *
 * @author Svetoslav Marinov (SLAVI)
 */
class Client {

  const VERSION = '1.2.0';

  const API_VERSION_2 = 2;
  const API_VERSION_3 = 3;

  /** @var string */
  protected $api_token = '';

  /** @var string */
  protected $account_id = '';

  /** @var string */
  protected $api_end_point = 'https://api.getdrip.com/';

  /** @var integer */
  protected $timeout = 30;

  /** @var integer */
  protected $connect_timeout = 30;

  /** @var callable */
  protected $guzzle_stack_constructor;

  const GET = "GET";

  const POST = "POST";

  const DELETE = "DELETE";

  const PUT = "PUT";

  /**
   * Accepts the token and saves it internally.
   *
   * @param string $api_token e.g. qsor48ughrjufyu2dadraasfa1212424
   * @param string $account_id e.g. 123456
   * @param array $options
   *               * `api_end_point` (mostly for Drip internal testing)
   *               * `guzzle_stack_constructor` (for test suite, may break at
   *   any time, do not use)
   *
   * @throws \Drip\Exception\InvalidAccountIdException
   * @throws \Drip\Exception\InvalidApiTokenException
   */
  public function __construct($api_token, $account_id, $options = []) {
    if (\array_key_exists('api_end_point', $options)) {
      $this->api_end_point = $options['api_end_point'];
    }
    // NOTE: For testing. Could break at any time, please do not depend on this.
    if (\array_key_exists('guzzle_stack_constructor', $options)) {
      $this->guzzle_stack_constructor = $options['guzzle_stack_constructor'];
    }
    // TODO: allow setting timeouts

    $api_token = trim($api_token);
    if (empty($api_token) || !preg_match('#^[\w-]+$#si', $api_token)) {
      throw new InvalidApiTokenException("Missing or invalid Drip API token.");
    }
    $this->api_token = $api_token;


    $account_id = trim($account_id);
    if (empty($account_id) || !preg_match('#^[\w-]+$#si', $account_id)) {
      throw new InvalidAccountIdException("Missing or invalid Drip API token.");
    }
    $this->account_id = $account_id;
  }

  /**
   * Requests the campaigns for the given account.
   *
   * @param array $params Set of arguments
   *                          - status (optional)
   *
   * @throws \Drip\Exception\InvalidArgumentException
   *
   * @return \Drip\ResponseInterface
   */
  public function get_campaigns($params) {
    if (isset($params['status'])) {
      if (!in_array($params['status'], ['active', 'draft', 'paused', 'all'])) {
        throw new InvalidArgumentException("Invalid campaign status.");
      }
    }

    return $this->make_request("$this->account_id/campaigns", $params);
  }

  /**
   * Fetch a campaign for the given account based on it's ID.
   *
   * @param array $params Set of arguments
   *                          - campaign_id (required)
   *
   * @throws \Drip\Exception\InvalidArgumentException
   *
   * @return \Drip\ResponseInterface
   */
  public function fetch_campaign($params) {
    if (empty($params['campaign_id'])) {
      throw new InvalidArgumentException("campaign_id was not specified");
    }

    $campaign_id = $params['campaign_id'];
    unset($params['campaign_id']); // clear it from the params

    return $this->make_request("$this->account_id/campaigns/$campaign_id", $params);
  }

  /**
   * Requests the accounts for the given account.
   * Parses the response JSON and returns an array which contains: id, name,
   * created_at etc
   *
   * @param void
   *
   * @return \Drip\ResponseInterface
   */
  public function get_accounts() {
    return $this->make_request('accounts');
  }

  /**
   * Sends a request to add a subscriber and returns its record or false
   *
   * @param array $params
   *
   * @return \Drip\ResponseInterface
   */
  public function create_or_update_subscriber($params) {
    // The API wants the params to be JSON encoded
    return $this->make_request(
      "$this->account_id/subscribers",
      ['subscribers' => [$params]],
      self::POST
    );
  }

  /**
   * Sends a request to add/update a batch (up to 1000) of subscribers
   *
   * @param array $params
   *
   * @return \Drip\ResponseInterface
   */
  public function create_or_update_subscribers($params) {
    return $this->make_request(
      "$this->account_id/subscribers/batches",
      $params,
      self::POST
    );
  }

  /**
   * Returns info regarding a particular subscriber
   *
   * @param array $params
   *
   * @throws \Drip\Exception\InvalidArgumentException
   *
   * @return \Drip\ResponseInterface
   */
  public function fetch_subscriber($params) {
    if (!empty($params['subscriber_id'])) {
      $subscriber_id = $params['subscriber_id'];
      unset($params['subscriber_id']); // clear it from the params
    }
    else {
      if (!empty($params['email'])) {
        $subscriber_id = $params['email'];
        unset($params['email']); // clear it from the params
      }
      else {
        throw new InvalidArgumentException("Subscriber ID or Email was not specified. You must specify either Subscriber ID or Email.");
      }
    }

    $subscriber_id = urlencode($subscriber_id);

    return $this->make_request("$this->account_id/subscribers/$subscriber_id");
  }

  /**
   * Subscribes a user to a given campaign for a given account.
   *
   * @param array $params
   *
   * @throws \Drip\Exception\InvalidArgumentException
   *
   * @return \Drip\ResponseInterface
   */
  public function subscribe_subscriber($params) {
    if (empty($params['campaign_id'])) {
      throw new InvalidArgumentException("Campaign ID not specified");
    }

    $campaign_id = $params['campaign_id'];
    unset($params['campaign_id']); // clear it from the params

    if (empty($params['email'])) {
      throw new InvalidArgumentException("Email not specified");
    }

    if (!isset($params['double_optin'])) {
      $params['double_optin'] = TRUE;
    }

    // The API wants the params to be JSON encoded
    $req_params = ['subscribers' => [$params]];

    return $this->make_request("$this->account_id/campaigns/$campaign_id/subscribers", $req_params, self::POST);
  }

  /**
   * Some keys are removed from the params so they don't get send with the
   * other data to Drip.
   *
   * @param array $params
   *
   * @throws \Drip\Exception\InvalidArgumentException
   *
   * @return \Drip\ResponseInterface
   */
  public function unsubscribe_subscriber($params) {
    if (!empty($params['subscriber_id'])) {
      $subscriber_id = $params['subscriber_id'];
      unset($params['subscriber_id']); // clear it from the params
    }
    else {
      if (!empty($params['email'])) {
        $subscriber_id = $params['email'];
        unset($params['email']); // clear it from the params
      }
      else {
        throw new InvalidArgumentException("Subscriber ID or Email was not specified. You must specify either Subscriber ID or Email.");
      }
    }

    $subscriber_id = urlencode($subscriber_id);
    return $this->make_request("$this->account_id/subscribers/$subscriber_id/unsubscribe", $params, self::POST);
  }

  /**
   * This calls POST /:account_id/tags to add the tag. It just returns some
   * status code no content
   *
   * @param $email
   * @param $tags
   *
   * @return \Drip\ResponseInterface
   */
  public function tag_subscriber($email, $tags) {
    $req_params = [
      'tags' => [],
    ];
    foreach ($tags as $tag) {
      $req_params['tags'][] = [
        'email' => $email,
        'tag' => $tag,
      ];
    }

    return $this->make_request("$this->account_id/tags", $req_params, self::POST);
  }

  /**
   * This calls DELETE /:account_id/tags to remove a single tag. It just returns
   * some status code no content
   *
   * @param $email
   * @param $tag
   *
   * @return \Drip\ResponseInterface
   */
  public function untag_subscriber($email, $tag) {
    $req_params = [
      'tags' => [
        [
          'email' => $email,
          'tag' => $tag,
        ],
      ],
    ];

    return $this->make_request("$this->account_id/tags", $req_params, self::DELETE);
  }

  /**
   * This calls DELETE /:account_id/subscribers/:id_or_email to delete a
   * subscriber.
   *
   * @param array $params
   *
   * @return \Drip\ResponseInterface
   * @throws \Drip\Exception\InvalidArgumentException
   */
  public function delete_subscriber($params) {
    if (!empty($params['subscriber_id'])) {
      $subscriber_id = $params['subscriber_id'];
      unset($params['subscriber_id']); // clear it from the params
    }
    else {
      if (!empty($params['email'])) {
        $subscriber_id = $params['email'];
        unset($params['email']); // clear it from the params
      }
      else {
        throw new InvalidArgumentException("Subscriber ID or Email was not specified. You must specify either Subscriber ID or Email.");
      }
    }

    $subscriber_id = urlencode($subscriber_id);
    return $this->make_request("$this->account_id/subscribers/$subscriber_id", $params, self::DELETE);
  }

  /**
   * Posts an event specified by the user.
   *
   * @param array $params
   * @param bool
   *
   * @throws \Drip\Exception\InvalidArgumentException
   *
   * @return \Drip\ResponseInterface
   */
  public function record_event($params) {
    if (empty($params['action'])) {
      throw new InvalidArgumentException("Action was not specified");
    }

    // The API wants the params to be JSON encoded
    $req_params = ['events' => [$params]];

    return $this->make_request("$this->account_id/events", $req_params, self::POST);
  }

  /**
   * Record cart activity.
   *
   * This is part of the Shopper Activity that uses v3 of Drip API.
   *
   * @param array $params
   *
   * @return \Drip\ResponseInterface
   * @throws \Drip\Exception\InvalidArgumentException
   */
  public function cart_activity($params) {
    // Person ID or email.
    if (!isset($params['email']) && !isset($params['person_id'])) {
      throw new InvalidArgumentException("Person ID or Email was not specified. You must specify either Person ID or Email.");
    }
    // Rest of the parameters we can check in bulk.
    $required_params = ['provider', 'action', 'cart_id', 'cart_url', 'items'];
    foreach ($required_params as $required_param) {
      if (!isset($params[$required_param]) || empty($params[$required_param])) {
        throw new InvalidArgumentException('You must specify the ' . $required_param . '.');
      }
    }

    return $this->make_request("$this->account_id/shopper_activity/cart", $params, self::POST, self::API_VERSION_3);
  }

  /**
   * Record order activity.
   *
   * This is part of the Shopper Activity that uses v3 of Drip API.
   *
   * @param array $params
   *
   * @return \Drip\ResponseInterface
   * @throws \Drip\Exception\InvalidArgumentException
   */
  public function order_activity($params) {
    // Person ID or email.
    if (!isset($params['email']) && !isset($params['person_id'])) {
      throw new InvalidArgumentException("Person ID or Email was not specified. You must specify either Person ID or Email.");
    }
    // Rest of the parameters we can check in bulk.
    $required_params = ['provider', 'action', 'order_id', 'items'];
    foreach ($required_params as $required_param) {
      if (!isset($params[$required_param]) || empty($params[$required_param])) {
        throw new InvalidArgumentException('You must specify the ' . $required_param . '.');
      }
    }

    return $this->make_request("$this->account_id/shopper_activity/order", $params, self::POST, self::API_VERSION_3);
  }

  /**
   * Record product activity.
   *
   * This is part of the Shopper Activity that uses v3 of Drip API.
   *
   * @param array $params
   *
   * @return \Drip\ResponseInterface
   * @throws \Drip\Exception\InvalidArgumentException
   */
  public function product_activity($params = []) {
    // Person ID or email.
    if (!isset($params['email']) && !isset($params['person_id'])) {
      throw new InvalidArgumentException("Person ID or Email was not specified. You must specify either Person ID or Email.");
    }
    // Rest of the parameters we can check in bulk.
    $required_params = ['provider', 'action', 'product_id', 'product_variant_id', 'name', 'price', ''];
    foreach ($required_params as $required_param) {
      if (!isset($params[$required_param]) || empty($params[$required_param])) {
        throw new InvalidArgumentException('You must specify the ' . $required_param . '.');
      }
    }

    return $this->make_request("$this->account_id/shopper_activity/product", $params, self::POST, self::API_VERSION_3);
  }

  /**
   * @return string
   */
  protected function user_agent() {
    return "Drip API PHP Wrapper (getdrip.com). Version " . self::VERSION;
  }

  /**
   * Determines whether the response is a success.
   *
   * @param int $code
   *
   * @return boolean
   */
  protected function is_success_response($code) {
    return $code >= 200 && $code <= 299;
  }

  /**
   *
   * @param string $url
   * @param array $params
   * @param int $req_method
   *
   * @return \Drip\ResponseInterface
   * @throws Exception
   */
  protected function make_request($url, $params = [], $req_method = self::GET, $api_version = self::API_VERSION_2) {
    if ($this->guzzle_stack_constructor) {
      // This can be replaced with `($this->guzzle_stack_constructor)()` once we drop PHP5 support.
      $fn = $this->guzzle_stack_constructor;
      $stack = $fn();
    }
    else {
      // @codeCoverageIgnoreStart
      $stack = \GuzzleHttp\HandlerStack::create();
      // @codeCoverageIgnoreEnd
    }
    $client = new \GuzzleHttp\Client([
      'base_uri' => $this->api_end_point . 'v' . $api_version . '/',
      'handler' => $stack,
    ]);

    $content_type = 'application/vnd.api+json';
    if ($api_version == self::API_VERSION_3) {
      $content_type = 'application/json';
    }

    $req_params = [
      'auth' => [$this->api_token, ''],
      'timeout' => $this->timeout,
      'connect_timeout' => $this->connect_timeout,
      'headers' => [
        'User-Agent' => $this->user_agent(),
        'Accept' => 'application/json, text/javascript, */*; q=0.01',
        'Content-Type' => $content_type,
      ],
      'http_errors' => FALSE,
    ];

    switch ($req_method) {
      case self::GET:
        $req_params['query'] = $params;
        break;
      case self::POST:
      case self::DELETE:
        // @codeCoverageIgnoreStart
      case self::PUT:
        // @codeCoverageIgnoreEnd
        if (isset($params['query'])) {
          $req_params['query'] = $params['query'];
          unset($params['query']);
        }
        $req_params['body'] = is_array($params) ? json_encode($params) : $params;
        break;
      default:
        // @codeCoverageIgnoreStart
        throw new UnexpectedHttpVerbException("Unexpected HTTP verb $req_method");
        break;
      // @codeCoverageIgnoreEnd
    }

    $res = $client->request($req_method, $url, $req_params);

    $success_klass = $this->is_success_response($res->getStatusCode()) ? \Drip\SuccessResponse::class : \Drip\ErrorResponse::class;
    return new $success_klass($url, $params, $res);
  }

}
