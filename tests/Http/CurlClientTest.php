<?php

declare(strict_types=1);

namespace AsterMD\VrioClient\Tests\Http;

use AsterMD\VrioClient\Http\CurlClient;
use AsterMD\VrioClient\Http\HttpClientInterface;
use AsterMD\VrioClient\Http\Request;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The transport is exercised with a scheme libcurl rejects before it opens a
 * socket, so this test performs no network I/O and no DNS lookup.
 */
#[CoversClass(\AsterMD\VrioClient\Http\CurlClient::class)]
final class CurlClientTest extends TestCase
{
    public function testItImplementsTheTransportInterface(): void
    {
        self::assertInstanceOf(HttpClientInterface::class, new CurlClient(5, 2));
    }

    public function testAnUnsupportedSchemeIsReportedAsATransportError(): void
    {
        $client   = new CurlClient(5, 2);
        $response = $client->send(new Request('GET', 'made-up-scheme://example.invalid/orders'));

        self::assertTrue($response->hasTransportError());
        self::assertNotSame('', $response->getTransportError());
        self::assertSame(0, $response->getStatusCode());
        self::assertSame('', $response->getBody());
    }

    public function testTheTransportErrorPathStillReturnsInfo(): void
    {
        $client   = new CurlClient(5, 2);
        $response = $client->send(new Request('POST', 'made-up-scheme://example.invalid/orders', [], '{"a":1}'));

        self::assertTrue($response->hasTransportError());
        self::assertSame(0, $response->getStatusCode());
    }
}
