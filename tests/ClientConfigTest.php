<?php

declare(strict_types=1);

namespace AsterMD\VrioClient\Tests;

use AsterMD\VrioClient\ClientConfig;
use AsterMD\VrioClient\Exception\VrioException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(\AsterMD\VrioClient\ClientConfig::class)]
final class ClientConfigTest extends TestCase
{
    private const API_KEY = 'test-api-key-not-a-real-credential';

    public function testItDefaultsToTheProviderHost(): void
    {
        $config = new ClientConfig(self::API_KEY);

        self::assertSame('api.vrio.app', $config->getHost());
        self::assertSame('https://api.vrio.app', $config->getBaseUrl());
    }

    public function testItAcceptsABareHost(): void
    {
        $config = new ClientConfig(self::API_KEY, ['host' => 'sandbox.vrio.app']);

        self::assertSame('https://sandbox.vrio.app', $config->getBaseUrl());
    }

    public function testABasePathIsAppendedWithoutDoubledSlashes(): void
    {
        $config = new ClientConfig(self::API_KEY, ['host' => 'api.vrio.app', 'basePath' => '/v1/']);

        self::assertSame('https://api.vrio.app/v1', $config->getBaseUrl());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedHostProvider(): array
    {
        return [
            'scheme'        => ['https://api.vrio.app'],
            'plain scheme'  => ['http://api.vrio.app'],
            'path included' => ['api.vrio.app/v1'],
            'query string'  => ['api.vrio.app?debug=1'],
            'embedded space' => ['api.vrio.app /v1'],
        ];
    }

    #[DataProvider('rejectedHostProvider')]
    public function testFullUrlsAreRejected(string $host): void
    {
        $this->expectException(VrioException::class);
        $this->expectExceptionMessage('bare hostname');

        new ClientConfig(self::API_KEY, ['host' => $host]);
    }

    public function testAnEmptyApiKeyIsRejected(): void
    {
        $this->expectException(VrioException::class);

        new ClientConfig('');
    }

    public function testAnEmptyHostFallsBackToTheDefault(): void
    {
        $config = new ClientConfig(self::API_KEY, ['host' => '  ']);

        self::assertSame(ClientConfig::DEFAULT_HOST, $config->getHost());
    }

    public function testTimeoutDefaultsAndOverrides(): void
    {
        $defaults = new ClientConfig(self::API_KEY);

        self::assertSame(ClientConfig::DEFAULT_TIMEOUT, $defaults->getTimeout());
        self::assertSame(ClientConfig::DEFAULT_CONNECT_TIMEOUT, $defaults->getConnectTimeout());

        $custom = new ClientConfig(self::API_KEY, ['timeout' => 5, 'connectTimeout' => 2]);

        self::assertSame(5, $custom->getTimeout());
        self::assertSame(2, $custom->getConnectTimeout());
    }

    public function testTheApiKeyIsReturnedVerbatim(): void
    {
        $config = new ClientConfig(self::API_KEY);

        self::assertSame(self::API_KEY, $config->getApiKey());
    }
}
