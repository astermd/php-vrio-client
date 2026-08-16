<?php

declare(strict_types=1);

namespace AsterMD\VrioClient;

use AsterMD\VrioClient\Exception\VrioException;

/**
 * Immutable connection settings for the VRIO API.
 *
 * The consumer supplies a bare hostname — never a full URL. This keeps the
 * scheme, path assembly and query encoding under the package's control.
 *
 * @package AsterMD\VrioClient
 */
final class ClientConfig
{
    /**
     * Host used when the consumer does not supply one.
     */
    public const DEFAULT_HOST = 'api.vrio.app';

    /**
     * Default transfer timeout in seconds.
     */
    public const DEFAULT_TIMEOUT = 30;

    /**
     * Default connection timeout in seconds.
     */
    public const DEFAULT_CONNECT_TIMEOUT = 10;

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $host;

    /**
     * @var string
     */
    private $basePath;

    /**
     * @var int
     */
    private $timeout;

    /**
     * @var int
     */
    private $connectTimeout;

    /**
     * @param string               $apiKey  VRIO API key. Supplied by the caller; the package ships no default.
     * @param array<string, mixed> $options {
     *     @type string $host           Bare hostname, e.g. "api.vrio.app". No scheme, no path.
     *     @type string $basePath       Optional path prefix appended to the host, e.g. "v1".
     *     @type int    $timeout        Transfer timeout in seconds.
     *     @type int    $connectTimeout Connection timeout in seconds.
     * }
     *
     * @throws VrioException When the API key is empty or the host is not a bare hostname.
     */
    public function __construct(string $apiKey, array $options = [])
    {
        if (trim($apiKey) === '') {
            throw new VrioException(Config::get('Messages.invalidAuth'));
        }

        $host = trim(self::stringOption($options, 'host', ''));
        if ($host === '') {
            $host = self::DEFAULT_HOST;
        }
        $this->assertBareHost($host);

        $this->apiKey         = $apiKey;
        $this->host           = $host;
        $this->basePath       = trim(self::stringOption($options, 'basePath', ''), '/');
        $this->timeout        = self::intOption($options, 'timeout', self::DEFAULT_TIMEOUT);
        $this->connectTimeout = self::intOption($options, 'connectTimeout', self::DEFAULT_CONNECT_TIMEOUT);
    }

    /**
     * Read a scalar option as a string, falling back when it is absent or not
     * scalar.
     *
     * @param array<string, mixed> $options
     * @param string               $key
     * @param string               $default
     *
     * @return string
     */
    private static function stringOption(array $options, string $key, string $default): string
    {
        if (!isset($options[$key]) || !is_scalar($options[$key])) {
            return $default;
        }

        return (string) $options[$key];
    }

    /**
     * @param array<string, mixed> $options
     * @param string               $key
     * @param int                  $default
     *
     * @return int
     */
    private static function intOption(array $options, string $key, int $default): int
    {
        if (!isset($options[$key]) || !is_numeric($options[$key])) {
            return $default;
        }

        return (int) $options[$key];
    }

    /**
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Absolute base URL with no trailing slash, e.g. "https://api.vrio.app".
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        $url = 'https://' . $this->host;
        if ($this->basePath !== '') {
            $url .= '/' . $this->basePath;
        }

        return $url;
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @return int
     */
    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }

    /**
     * Reject anything that is not a bare hostname.
     *
     * @param string $host
     *
     * @return void
     *
     * @throws VrioException
     */
    private function assertBareHost(string $host): void
    {
        $looksLikeUrl = strpos($host, '://') !== false
            || strpos($host, '/') !== false
            || strpos($host, ' ') !== false
            || strpos($host, '?') !== false;

        if ($looksLikeUrl) {
            throw new VrioException(Config::get('Messages.invalidHost'));
        }
    }
}
