<?php

declare(strict_types=1);

namespace AsterMD\VrioClient\Tests;

use AsterMD\VrioClient\ClientConfig;
use AsterMD\VrioClient\Http\Response;
use AsterMD\VrioClient\Logging\DebugLogger;
use AsterMD\VrioClient\Order;
use AsterMD\VrioClient\Tests\Support\ClientTestCase;
use AsterMD\VrioClient\Tests\Support\MockHttpClient;
use stdClass;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\AsterMD\VrioClient\Vrio::class)]
final class ResponseEnvelopeTest extends ClientTestCase
{
    public function testASuccessfulBodyIsWrappedInTheEnvelope(): void
    {
        $this->http->queue(new Response(200, '{"id":"ord_1"}', ['http_code' => 200]));

        $envelope = $this->arrayEnvelope($this->client()->getOrder('ord_1')->getInArray());

        self::assertTrue($envelope['success']);
        self::assertSame('', $envelope['message']);
        self::assertSame(['id' => 'ord_1'], $envelope['data']);
        self::assertArrayNotHasKey('validation_code', $envelope);
    }

    public function testAnErrorBodyPopulatesMessageAndValidationCode(): void
    {
        $body = '{"error":{"message":"Order not found","code":"not_found"}}';
        $this->http->queue(new Response(404, $body, ['http_code' => 404]));

        $envelope = $this->arrayEnvelope($this->client()->getOrder('ord_missing')->getInArray());

        self::assertFalse($envelope['success']);
        self::assertSame('Order not found', $envelope['message']);
        self::assertSame('not_found', $envelope['validation_code']);
    }

    public function testAnErrorWithoutACodeStillReportsFailure(): void
    {
        $this->http->queue(new Response(422, '{"error":{"message":"Invalid"}}', ['http_code' => 422]));

        $envelope = $this->arrayEnvelope($this->client()->getOrder('ord_1')->getInArray());

        self::assertFalse($envelope['success']);
        self::assertSame('', $envelope['validation_code']);
    }

    public function testTheRawResponseIsAJsonString(): void
    {
        $this->http->queue(new Response(200, '{"id":"ord_1"}', ['http_code' => 200]));

        /** @var string $raw */
        $raw = $this->client()->getOrder('ord_1')->get()['response'];

        self::assertJson($raw);
    }

    public function testTheObjectResponseDecodesToAnObject(): void
    {
        $this->http->queue(new Response(200, '{"id":"ord_1"}', ['http_code' => 200]));

        $envelope = $this->objectEnvelope($this->client()->getOrder('ord_1')->getInObject());

        /** @var stdClass $data */
        $data = $envelope->data;

        self::assertTrue($envelope->success);
        self::assertSame('ord_1', $data->id);
    }

    public function testATransportErrorIsSurfacedAsCurlError(): void
    {
        $this->http->queue(new Response(0, '', [], 'Could not resolve host'));

        $client = $this->client();
        $client->searchOrder();

        self::assertSame('Could not resolve host', $client->get()['response']);
        self::assertSame(['curlError' => 'Could not resolve host'], $client->getInArray()['response']);

        $envelope = $this->objectEnvelope($client->getInObject());
        self::assertSame('Could not resolve host', $envelope->curlError);
    }

    public function testANonJsonProviderBodyYieldsNullData(): void
    {
        $this->http->queue(new Response(502, 'Bad Gateway', ['http_code' => 502]));

        $envelope = $this->arrayEnvelope($this->client()->searchOrder()->getInArray());

        self::assertTrue($envelope['success']);
        self::assertNull($envelope['data']);
    }

    public function testHeaderRequiredAttachesTransportMetadata(): void
    {
        $http = new MockHttpClient();
        $http->queue(new Response(200, '{"id":"ord_1"}', ['http_code' => 200, 'total_time' => 0.12]));

        $order = new Order(new ClientConfig(self::API_KEY), $http, new DebugLogger(false));
        $order->headerRequired = true;
        $order->searchOrder();

        /** @var array{content: string, header: array<string, mixed>} $raw */
        $raw = $order->getResponse();
        self::assertJson($raw['content']);
        self::assertSame(200, $raw['header']['http_code']);

        /** @var array{content: array<string, mixed>, header: array<string, mixed>} $decoded */
        $decoded = $order->getArrayResponse();
        self::assertSame(['id' => 'ord_1'], $decoded['content']['data']);
        self::assertSame(0.12, $decoded['header']['total_time']);

        /** @var stdClass $wrapper */
        $wrapper = $order->getObjectResponse();
        /** @var stdClass $content */
        $content = $wrapper->content;
        /** @var stdClass $data */
        $data = $content->data;
        /** @var stdClass $header */
        $header = $wrapper->header;

        self::assertSame('ord_1', $data->id);
        self::assertSame(200, $header->http_code);
    }

    public function testSetProxyAppliesToTheNextRequest(): void
    {
        $http  = new MockHttpClient();
        $order = new Order(new ClientConfig(self::API_KEY), $http, new DebugLogger(false));

        $order->setProxy('proxy.example.test:3128', 'user:placeholder');
        $order->searchOrder();

        self::assertSame('proxy.example.test:3128', $http->lastRequest()->getProxyUrl());
        self::assertSame('user:placeholder', $http->lastRequest()->getProxyCredentials());

        $order->searchOrder();

        self::assertFalse($http->lastRequest()->hasProxy());
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function arrayEnvelope(array $result): array
    {
        self::assertIsArray($result['response']);

        /** @var array<string, mixed> $envelope */
        $envelope = $result['response'];

        return $envelope;
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return stdClass
     */
    private function objectEnvelope(array $result): stdClass
    {
        self::assertInstanceOf(stdClass::class, $result['response']);

        /** @var stdClass $envelope */
        $envelope = $result['response'];

        return $envelope;
    }
}
