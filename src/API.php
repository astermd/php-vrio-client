<?php

declare(strict_types=1);

namespace AsterMD\VrioClient;

use AsterMD\VrioClient\Exception\VrioException;
use AsterMD\VrioClient\Http\CurlClient;
use AsterMD\VrioClient\Http\HttpClientInterface;
use AsterMD\VrioClient\Logging\DebugLogger;

/**
 * Entry point for the VRIO API.
 *
 * Construct it with your API key, call any resource method, then read the
 * result with get(), getInArray() or getInObject():
 *
 *     $api  = new API($apiKey, ['host' => 'api.vrio.app']);
 *     $data = $api->searchOrder(['with' => 'items'])->getInArray();
 *
 * The API key is a required argument. This package ships no default
 * credentials and reads none from the environment.
 *
 * @method self getCampaignItems(string $campaignId, array<string, mixed> $params = [])
 * @method self getCustomer(string $customerId, array<string, mixed> $params = [])
 * @method self validateDiscount(array<string, mixed> $params = [])
 * @method self calculateDiscount(array<string, mixed> $params = [])
 * @method self searchOffer(array<string, mixed> $params = [])
 * @method self searchOrder(array<string, mixed> $params = [])
 * @method self getOrder(string $orderId, array<string, mixed> $params = [])
 * @method self addOrder(array<string, mixed> $params = [])
 * @method self editOrder(array<string, mixed> $params = [])
 * @method self processOrder(string $orderId, array<string, mixed> $params = [])
 * @method self completeOrder(string $orderId, array<string, mixed> $params = [])
 * @method self authorizeOrder(string $orderId, array<string, mixed> $params = [])
 * @method self captureOrder(string $orderId, array<string, mixed> $params = [])
 * @method self addOrderNote(string $orderId, array<string, mixed> $params = [])
 * @method self getRoute(string $routeId, array<string, mixed> $params = [])
 * @method self createCart(array<string, mixed> $params = [])
 * @method self createPaypalToken(array<string, mixed> $params = [])
 * @method self getPaypalData(array<string, mixed> $params = [])
 *
 * @package AsterMD\VrioClient
 */
final class API
{
    /**
     * Resource method to the class that implements it.
     *
     * @var array<string, class-string<Vrio>>
     */
    private const METHOD_MAP = [
        'getCampaignItems'  => Campaign::class,
        'getCustomer'       => Customer::class,
        'validateDiscount'  => Discount::class,
        'calculateDiscount' => Discount::class,
        'searchOffer'       => Offer::class,
        'searchOrder'       => Order::class,
        'getOrder'          => Order::class,
        'addOrder'          => Order::class,
        'editOrder'         => Order::class,
        'processOrder'      => Order::class,
        'completeOrder'     => Order::class,
        'authorizeOrder'    => Order::class,
        'captureOrder'      => Order::class,
        'addOrderNote'      => Order::class,
        'getRoute'          => Route::class,
        'createCart'        => Cart::class,
        'createPaypalToken' => Cart::class,
        'getPaypalData'     => Cart::class,
    ];

    /**
     * @var ClientConfig
     */
    private $config;

    /**
     * @var HttpClientInterface
     */
    private $http;

    /**
     * @var DebugLogger
     */
    private $logger;

    /**
     * @var array<class-string<Vrio>, Vrio>
     */
    private $resources = [];

    /**
     * @var Vrio|null
     */
    private $desiredInstance;

    /**
     * @var string|null
     */
    private $proxyUrl;

    /**
     * @var string|null
     */
    private $proxyCredentials;

    /**
     * @param string                   $apiKey  VRIO API key. Required; there is no default.
     * @param array<string, mixed>     $options {
     *     @type string   $host               Bare hostname. Defaults to ClientConfig::DEFAULT_HOST.
     *     @type string   $basePath           Optional path prefix, e.g. "v1".
     *     @type int      $timeout            Transfer timeout in seconds. Default 30.
     *     @type int      $connectTimeout     Connection timeout in seconds. Default 10.
     *     @type bool     $debug              Enable request/response logging. Default false.
     *     @type bool     $debugRedact        Redact logged headers and bodies. Default true.
     *     @type string   $debugFile          Base path for the built-in dated file sink.
     *     @type int      $debugRetentionDays Days of log history to keep. Default 7, 0 keeps all.
     *     @type string   $debugTimezone      IANA timezone for log timestamps. Default "UTC".
     *     @type callable $debugSink          Replaces the file sink: function (string $entry): void
     * }
     * @param HttpClientInterface|null $http    Custom transport. Defaults to cURL.
     *
     * @throws VrioException When the API key is empty, the host is not bare, or
     *                       debug logging is enabled without a destination.
     */
    public function __construct(string $apiKey, array $options = [], ?HttpClientInterface $http = null)
    {
        $this->config = new ClientConfig($apiKey, $options);
        $this->logger = DebugLogger::fromOptions($options);
        $this->http   = $http ?? new CurlClient(
            $this->config->getTimeout(),
            $this->config->getConnectTimeout()
        );
    }

    /**
     * Route the next call through an HTTP proxy.
     *
     * The setting applies to the next resource call only.
     *
     * @param string $proxyUrl         Proxy address, e.g. "proxy.example.com:8080".
     * @param string $proxyCredentials Optional "user:password" credentials.
     *
     * @return self
     */
    public function withProxy(string $proxyUrl, string $proxyCredentials = ''): self
    {
        if ($proxyUrl !== '') {
            $this->proxyUrl         = $proxyUrl;
            $this->proxyCredentials = $proxyCredentials !== '' ? $proxyCredentials : null;
        }

        return $this;
    }

    /**
     * Dispatch a resource method to the class that implements it.
     *
     * @param string       $method
     * @param array<mixed> $arguments
     *
     * @return self
     *
     * @throws VrioException When no resource implements the method.
     */
    public function __call(string $method, array $arguments): self
    {
        $resource = $this->resourceFor($method);

        if ($this->proxyUrl !== null) {
            $resource->setProxy($this->proxyUrl, $this->proxyCredentials);
            $this->proxyUrl         = null;
            $this->proxyCredentials = null;
        }

        $callable = [$resource, $method];
        if (!is_callable($callable)) {
            throw new VrioException(Config::get('Messages.methodNotFound'));
        }

        $this->desiredInstance = $resource;
        $callable(...$arguments);

        return $this;
    }

    /**
     * Raw response envelope from the most recent call.
     *
     * @param bool $payloadFlag Include the request URL and payload alongside the response.
     *
     * @return array<string, mixed>
     *
     * @throws VrioException When no resource method has been called yet.
     */
    public function get(bool $payloadFlag = false): array
    {
        $resource = $this->requireInstance();

        $result = ['response' => $resource->getResponse()];
        if ($payloadFlag) {
            $result['payload'] = $resource->getPayloadInfo();
        }

        return $result;
    }

    /**
     * Response from the most recent call, decoded into an array.
     *
     * @param bool $payloadFlag Include the request URL and payload alongside the response.
     *
     * @return array<string, mixed>
     *
     * @throws VrioException When no resource method has been called yet.
     */
    public function getInArray(bool $payloadFlag = false): array
    {
        $resource = $this->requireInstance();

        $result = ['response' => $resource->getArrayResponse()];
        if ($payloadFlag) {
            $result['payload'] = $resource->getPayloadInfo();
        }

        return $result;
    }

    /**
     * Response from the most recent call, decoded into an object.
     *
     * @param bool $payloadFlag Include the request URL and payload alongside the response.
     *
     * @return array<string, mixed>
     *
     * @throws VrioException When no resource method has been called yet.
     */
    public function getInObject(bool $payloadFlag = false): array
    {
        $resource = $this->requireInstance();

        $result = ['response' => $resource->getObjectResponse()];
        if ($payloadFlag) {
            $result['payload'] = (object) $resource->getPayloadInfo();
        }

        return $result;
    }

    /**
     * Resource methods this client can dispatch.
     *
     * @return string[]
     */
    public static function supportedMethods(): array
    {
        return array_keys(self::METHOD_MAP);
    }

    /**
     * @param string $method
     *
     * @return Vrio
     *
     * @throws VrioException
     */
    private function resourceFor(string $method): Vrio
    {
        if (!isset(self::METHOD_MAP[$method])) {
            throw new VrioException(Config::get('Messages.methodNotFound'));
        }

        $class = self::METHOD_MAP[$method];
        if (!isset($this->resources[$class])) {
            $this->resources[$class] = new $class($this->config, $this->http, $this->logger);
        }

        return $this->resources[$class];
    }

    /**
     * @return Vrio
     *
     * @throws VrioException
     */
    private function requireInstance(): Vrio
    {
        if ($this->desiredInstance === null) {
            throw new VrioException(Config::get('Messages.apiMethodInvoked'));
        }

        return $this->desiredInstance;
    }
}
