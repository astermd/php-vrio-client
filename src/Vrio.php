<?php

declare(strict_types=1);

namespace AsterMD\VrioClient;

use AsterMD\VrioClient\Exception\VrioException;
use AsterMD\VrioClient\Http\HttpClientInterface;
use AsterMD\VrioClient\Http\Request;
use AsterMD\VrioClient\Logging\DebugLogger;
use stdClass;

/**
 * Base class for every VRIO API resource.
 *
 * Holds the connection settings, assembles the request, hands it to the
 * transport, and normalises the response into the envelope the accessor
 * methods return.
 *
 * Credentials travel in the request headers only. They are never merged into
 * the request body and never appear in {@see self::getPayloadInfo()}.
 *
 * @package AsterMD\VrioClient
 */
abstract class Vrio
{
    /**
     * Include transport metadata alongside the response body.
     *
     * @var bool
     */
    public $headerRequired = false;

    /**
     * @var ClientConfig
     */
    protected $config;

    /**
     * @var HttpClientInterface
     */
    protected $http;

    /**
     * @var DebugLogger
     */
    protected $logger;

    /**
     * Request body fields set by the calling resource method.
     *
     * @var array<string, mixed>
     */
    protected $fields = [];

    /**
     * Path below the base URL, e.g. "orders/42/capture".
     *
     * @var string
     */
    protected $section = '';

    /**
     * Query string parameters for GET calls.
     *
     * @var array<string, mixed>
     */
    private $query = [];

    /**
     * @var string
     */
    private $apiUrl = '';

    /**
     * Normalised response envelope, or the transport error message.
     *
     * @var string
     */
    private $envelope = '';

    /**
     * @var array<string, mixed>
     */
    private $transportInfo = [];

    /**
     * @var bool
     */
    private $checkError = false;

    /**
     * Payload snapshot kept for getPayloadInfo() after the request state resets.
     *
     * @var array<string, mixed>
     */
    private $lastPayload = [];

    /**
     * @var string
     */
    private $lastUrl = '';

    /**
     * @var string|null
     */
    private $proxyUrl;

    /**
     * @var string|null
     */
    private $proxyCredentials;

    /**
     * @param ClientConfig        $config
     * @param HttpClientInterface $http
     * @param DebugLogger         $logger
     */
    public function __construct(ClientConfig $config, HttpClientInterface $http, DebugLogger $logger)
    {
        $this->config = $config;
        $this->http   = $http;
        $this->logger = $logger;
    }

    /**
     * Route the next request through an HTTP proxy.
     *
     * Cleared automatically once the request completes.
     *
     * @param string|null $proxyUrl         Proxy address, e.g. "proxy.example.com:8080".
     * @param string|null $proxyCredentials "user:password" credentials, when the proxy requires them.
     *
     * @return void
     */
    public function setProxy(?string $proxyUrl, ?string $proxyCredentials = null): void
    {
        $this->proxyUrl         = $proxyUrl;
        $this->proxyCredentials = $proxyCredentials;
    }

    /**
     * Raw response envelope as a JSON string.
     *
     * With $headerRequired the return is an array of "content" and "header".
     *
     * @return string|array<string, mixed>
     */
    public function getResponse()
    {
        if ($this->headerRequired && !$this->checkError) {
            return [
                'content' => $this->envelope,
                'header'  => $this->transportInfo,
            ];
        }

        return $this->envelope;
    }

    /**
     * Response envelope decoded into an array.
     *
     * @return array<string, mixed>
     *
     * @throws VrioException When the envelope is not valid JSON.
     */
    public function getArrayResponse(): array
    {
        if ($this->checkError) {
            return ['curlError' => $this->envelope];
        }

        /** @var array<string, mixed> $decoded */
        $decoded = $this->isJson($this->envelope, true);

        if ($this->headerRequired) {
            return [
                'content' => $decoded,
                'header'  => $this->transportInfo,
            ];
        }

        return $decoded;
    }

    /**
     * Response envelope decoded into an object.
     *
     * @return object
     *
     * @throws VrioException When the envelope is not valid JSON.
     */
    public function getObjectResponse(): object
    {
        if ($this->checkError) {
            $error            = new stdClass();
            $error->curlError = $this->envelope;

            return $error;
        }

        /** @var object $decoded */
        $decoded = $this->isJson($this->envelope, false);

        if ($this->headerRequired) {
            $wrapper          = new stdClass();
            $wrapper->content = $decoded;
            $wrapper->header  = (object) $this->transportInfo;

            return $wrapper;
        }

        return $decoded;
    }

    /**
     * The URL and payload used by the most recent call.
     *
     * Credentials are deliberately absent — this value is safe to log or
     * return to an end user.
     *
     * @return array<string, mixed>
     */
    public function getPayloadInfo(): array
    {
        return array_merge(['endPoint' => $this->lastUrl], $this->lastPayload);
    }

    /**
     * Build the absolute URL for the current section.
     *
     * @return void
     */
    protected function setApiUrl(): void
    {
        $path         = str_replace(' ', '', ltrim($this->section, '/'));
        $this->apiUrl = $this->config->getBaseUrl() . '/' . $path;
    }

    /**
     * Issue a GET request, appending $params as the query string.
     *
     * @param array<string, mixed> $params
     *
     * @return void
     */
    protected function sendGet(array $params = []): void
    {
        $this->query = $params;
        $this->setApiUrl();
        $this->apiUrl = rtrim($this->apiUrl, '/');

        if ($params !== []) {
            $this->apiUrl .= '?' . http_build_query($params);
        }

        $this->request('GET', null);
    }

    /**
     * @return void
     */
    protected function sendPost(): void
    {
        $this->setApiUrl();
        $this->request('POST', $this->encodeFields());
    }

    /**
     * @return void
     */
    protected function sendPatch(): void
    {
        $this->setApiUrl();
        $this->request('PATCH', $this->encodeFields());
    }

    /**
     * @return void
     */
    protected function sendPut(): void
    {
        $this->setApiUrl();
        $this->request('PUT', $this->encodeFields());
    }

    /**
     * @return void
     */
    protected function sendDelete(): void
    {
        $this->setApiUrl();
        $this->request('DELETE', null);
    }

    /**
     * Abort the current call because a mandatory argument was missing.
     *
     * @return void
     *
     * @throws VrioException
     */
    protected function requiredFields(): void
    {
        throw new VrioException(Config::get('Messages.requiredFields'));
    }

    /**
     * Decode a JSON string.
     *
     * @param string $string
     * @param bool   $arrFlag Decode objects into associative arrays.
     *
     * @return mixed
     *
     * @throws VrioException When the string is not valid JSON.
     */
    protected function isJson(string $string, bool $arrFlag = false)
    {
        /** @var mixed $decoded */
        $decoded = json_decode($string, $arrFlag);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new VrioException(Config::get('Messages.jsonFormatError'));
        }

        return $decoded;
    }

    /**
     * Send the assembled request and normalise the outcome.
     *
     * @param string      $method
     * @param string|null $body
     *
     * @return void
     */
    private function request(string $method, ?string $body): void
    {
        $this->lastUrl     = $this->apiUrl;
        $this->lastPayload = array_merge($this->query, $this->fields);

        $request = new Request(
            $method,
            $this->apiUrl,
            $this->buildHeaders(),
            $body,
            $this->proxyUrl,
            $this->proxyCredentials
        );

        $response = $this->http->send($request);
        $this->logger->log($request, $response);

        $this->transportInfo = $response->getInfo();

        if ($response->hasTransportError()) {
            $this->checkError = true;
            $this->envelope   = $response->getTransportError();
        } else {
            $this->checkError = false;
            $this->envelope   = $this->buildEnvelope($response->getBody());
        }

        $this->reset();
    }

    /**
     * Wrap the provider's body in the package's success/message/data envelope.
     *
     * @param string $body
     *
     * @return string
     */
    private function buildEnvelope(string $body): string
    {
        // Decoded twice on purpose: the array form is inspected for the error
        // node, while the object form is handed back untouched as "data".
        /** @var mixed $inspectable */
        $inspectable = json_decode($body, true);

        /** @var mixed $error */
        $error = (is_array($inspectable) && isset($inspectable['error'])) ? $inspectable['error'] : null;

        $envelope          = new stdClass();
        $envelope->success = $error === null;
        $envelope->message = (is_array($error) && isset($error['message']) && is_scalar($error['message']))
            ? (string) $error['message']
            : '';
        $envelope->data    = json_decode($body);

        if ($error !== null) {
            $envelope->validation_code = (is_array($error) && isset($error['code']))
                ? $error['code']
                : '';
        }

        $encoded = json_encode($envelope);

        return $encoded === false ? '{"success":false,"message":"","data":null}' : $encoded;
    }

    /**
     * @return string[]
     */
    private function buildHeaders(): array
    {
        return [
            'Content-Type: application/json',
            'hostname: ' . $this->config->getHost(),
            'X-Api-Key: ' . $this->config->getApiKey(),
        ];
    }

    /**
     * Encode the request body, keeping an empty payload a JSON object.
     *
     * @return string
     */
    private function encodeFields(): string
    {
        $encoded = json_encode($this->fields === [] ? new stdClass() : $this->fields);

        return $encoded === false ? '{}' : $encoded;
    }

    /**
     * Clear per-request state so a reused instance cannot leak the previous
     * call's fields into the next one.
     *
     * @return void
     */
    private function reset(): void
    {
        $this->apiUrl           = '';
        $this->section          = '';
        $this->fields           = [];
        $this->query            = [];
        $this->proxyUrl         = null;
        $this->proxyCredentials = null;
    }
}
