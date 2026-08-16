<?php

declare(strict_types=1);

namespace AsterMD\VrioClient\Tests;

use AsterMD\VrioClient\API;
use AsterMD\VrioClient\Exception\VrioException;
use AsterMD\VrioClient\Tests\Support\ClientTestCase;
use AsterMD\VrioClient\Tests\Support\MockHttpClient;
use stdClass;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\AsterMD\VrioClient\API::class)]
final class ApiTest extends ClientTestCase
{
    public function testAnApiKeyIsRequired(): void
    {
        $this->expectException(VrioException::class);
        $this->expectExceptionMessage('An API key is required');

        new API('', [], new MockHttpClient());
    }

    public function testAWhitespaceOnlyApiKeyIsRejected(): void
    {
        $this->expectException(VrioException::class);

        new API('   ', [], new MockHttpClient());
    }

    public function testTheDefaultHostIsUsedWhenNoneIsGiven(): void
    {
        $this->client()->searchOrder();

        self::assertSame('https://api.vrio.app/orders', $this->http->lastRequest()->getUrl());
    }

    public function testAnExplicitHostIsHonoured(): void
    {
        $this->client(['host' => 'sandbox.vrio.app'])->searchOrder();

        $request = $this->http->lastRequest();

        self::assertSame('https://sandbox.vrio.app/orders', $request->getUrl());
        self::assertContains('hostname: sandbox.vrio.app', $request->getHeaders());
    }

    public function testABasePathIsPrependedToEveryRoute(): void
    {
        $this->client(['basePath' => 'v1'])->searchOrder();

        self::assertSame('https://api.vrio.app/v1/orders', $this->http->lastRequest()->getUrl());
    }

    public function testUnknownMethodsAreRejected(): void
    {
        $this->expectException(VrioException::class);
        $this->expectExceptionMessage('No such method found');

        /** @phpstan-ignore-next-line Intentionally calling a method that does not exist. */
        $this->client()->thisMethodDoesNotExist();
    }

    public function testAccessorsFailBeforeAnyMethodIsInvoked(): void
    {
        $this->expectException(VrioException::class);
        $this->expectExceptionMessage('No API method has been invoked yet');

        $this->client()->get();
    }

    public function testResourceCallsAreChainable(): void
    {
        $client = $this->client();

        self::assertSame($client, $client->searchOrder());
    }

    public function testPayloadInfoIsOmittedByDefault(): void
    {
        $result = $this->client()->searchOrder(['with' => 'items'])->get();

        self::assertArrayNotHasKey('payload', $result);
        self::assertArrayHasKey('response', $result);
    }

    public function testPayloadInfoIsReturnedOnRequest(): void
    {
        $result = $this->client()->searchOrder(['with' => 'items'])->get(true);

        self::assertSame(
            ['endPoint' => 'https://api.vrio.app/orders?with=items', 'with' => 'items'],
            $result['payload']
        );
    }

    public function testPayloadInfoNeverContainsTheApiKey(): void
    {
        $client = $this->client();

        $client->addOrder(['email' => 'buyer@example.test']);
        $encoded = json_encode($client->get(true));

        self::assertIsString($encoded);
        self::assertStringNotContainsString(self::API_KEY, $encoded);
        self::assertStringNotContainsString('apiKey', $encoded);
    }

    public function testPayloadInfoIsAnObjectForGetInObject(): void
    {
        $result = $this->client()->searchOrder()->getInObject(true);

        self::assertInstanceOf(stdClass::class, $result['payload']);

        /** @var stdClass $payload */
        $payload = $result['payload'];

        self::assertSame('https://api.vrio.app/orders', $payload->endPoint);
    }

    public function testTheApiKeyIsSentAsAHeaderAndNeverInTheBody(): void
    {
        $this->client()->addOrder(['email' => 'buyer@example.test']);

        $request = $this->http->lastRequest();

        self::assertContains('X-Api-Key: ' . self::API_KEY, $request->getHeaders());
        self::assertSame('{"email":"buyer@example.test"}', $request->getBody());
    }

    public function testWithProxyAppliesToTheNextCallOnly(): void
    {
        $client = $this->client();

        $client->withProxy('proxy.example.test:8080', 'user:secret-placeholder')->searchOrder();
        $first = $this->http->lastRequest();

        self::assertTrue($first->hasProxy());
        self::assertSame('proxy.example.test:8080', $first->getProxyUrl());
        self::assertSame('user:secret-placeholder', $first->getProxyCredentials());

        $client->searchOrder();
        $second = $this->http->lastRequest();

        self::assertFalse($second->hasProxy());
        self::assertNull($second->getProxyUrl());
    }

    public function testWithProxyIsChainableAndIgnoresAnEmptyAddress(): void
    {
        $client = $this->client();

        self::assertSame($client, $client->withProxy(''));

        $client->searchOrder();

        self::assertFalse($this->http->lastRequest()->hasProxy());
    }

    public function testSupportedMethodsListsEveryResourceMethod(): void
    {
        $methods = API::supportedMethods();

        self::assertCount(18, $methods);
        self::assertContains('captureOrder', $methods);
        self::assertContains('getPaypalData', $methods);
        self::assertNotContains('get', $methods);
    }
}
