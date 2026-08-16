<?php

declare(strict_types=1);

namespace AsterMD\VrioClient\Tests\Support;

use AsterMD\VrioClient\API;
use AsterMD\VrioClient\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Shared fixtures for tests that exercise the client end to end.
 */
abstract class ClientTestCase extends TestCase
{
    /**
     * Obviously fake placeholder. Never a real credential.
     */
    public const API_KEY = 'test-api-key-not-a-real-credential';

    /**
     * @var MockHttpClient
     */
    protected $http;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->http = new MockHttpClient();
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return API
     */
    protected function client(array $options = []): API
    {
        return new API(self::API_KEY, $options, $this->http);
    }

    /**
     * Assert the recorded request matches the expected shape.
     *
     * @param string      $method
     * @param string      $url
     * @param string|null $body
     *
     * @return Request
     */
    protected function assertRequest(string $method, string $url, ?string $body = null): Request
    {
        $request = $this->http->lastRequest();

        self::assertSame($method, $request->getMethod());
        self::assertSame($url, $request->getUrl());
        self::assertSame($body, $request->getBody());
        self::assertSame(
            [
                'Content-Type: application/json',
                'hostname: api.vrio.app',
                'X-Api-Key: ' . self::API_KEY,
            ],
            $request->getHeaders()
        );

        return $request;
    }
}
