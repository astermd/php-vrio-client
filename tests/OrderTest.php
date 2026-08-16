<?php

declare(strict_types=1);

namespace AsterMD\VrioClient\Tests;

use AsterMD\VrioClient\Exception\VrioException;
use AsterMD\VrioClient\Tests\Support\ClientTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\AsterMD\VrioClient\Order::class)]
final class OrderTest extends ClientTestCase
{
    public function testSearchOrderWithoutParameters(): void
    {
        $this->client()->searchOrder();

        $this->assertRequest('GET', 'https://api.vrio.app/orders');
    }

    public function testSearchOrderWithParameters(): void
    {
        $this->client()->searchOrder(['with' => 'items', 'status' => 'complete']);

        $this->assertRequest('GET', 'https://api.vrio.app/orders?with=items&status=complete');
    }

    public function testGetOrder(): void
    {
        $this->client()->getOrder('ord_1', ['with' => 'items']);

        $this->assertRequest('GET', 'https://api.vrio.app/orders/ord_1?with=items');
    }

    public function testGetOrderRejectsAnEmptyId(): void
    {
        $this->expectException(VrioException::class);

        $this->client()->getOrder('');
    }

    public function testAddOrder(): void
    {
        $this->client()->addOrder([
            'connection_id' => 'con_1',
            'campaign_id'   => 'camp_1',
            'email'         => 'buyer@example.test',
        ]);

        $this->assertRequest(
            'POST',
            'https://api.vrio.app/orders',
            '{"connection_id":"con_1","campaign_id":"camp_1","email":"buyer@example.test"}'
        );
    }

    public function testEditOrderIssuesAPatch(): void
    {
        $this->client()->editOrder(['order_id' => 'ord_1', 'note' => 'updated']);

        $this->assertRequest(
            'PATCH',
            'https://api.vrio.app/orders/ord_1',
            '{"order_id":"ord_1","note":"updated"}'
        );
    }

    public function testEditOrderRequiresAnOrderId(): void
    {
        $this->expectException(VrioException::class);

        $this->client()->editOrder(['note' => 'updated']);
    }

    public function testProcessOrderSendsAnEmptyJsonObjectWhenNoBodyIsGiven(): void
    {
        $this->client()->processOrder('ord_1');

        $this->assertRequest('POST', 'https://api.vrio.app/orders/ord_1/process', '{}');
    }

    public function testProcessOrderRejectsAnEmptyId(): void
    {
        $this->expectException(VrioException::class);

        $this->client()->processOrder('');
    }

    public function testCompleteOrder(): void
    {
        $this->client()->completeOrder('ord_1', ['reason' => 'shipped']);

        $this->assertRequest(
            'POST',
            'https://api.vrio.app/orders/ord_1/complete',
            '{"reason":"shipped"}'
        );
    }

    public function testCompleteOrderRejectsAnEmptyId(): void
    {
        $this->expectException(VrioException::class);

        $this->client()->completeOrder('');
    }

    public function testAuthorizeOrder(): void
    {
        $this->client()->authorizeOrder('ord_1', ['amount' => 1000]);

        $this->assertRequest(
            'POST',
            'https://api.vrio.app/orders/ord_1/authorize',
            '{"amount":1000}'
        );
    }

    public function testAuthorizeOrderRejectsAnEmptyId(): void
    {
        $this->expectException(VrioException::class);

        $this->client()->authorizeOrder('');
    }

    public function testCaptureOrder(): void
    {
        $this->client()->captureOrder('ord_1', ['amount' => 1000]);

        $this->assertRequest(
            'POST',
            'https://api.vrio.app/orders/ord_1/capture',
            '{"amount":1000}'
        );
    }

    public function testCaptureOrderRejectsAnEmptyId(): void
    {
        $this->expectException(VrioException::class);

        $this->client()->captureOrder('');
    }

    public function testAddOrderNote(): void
    {
        $this->client()->addOrderNote('ord_1', ['body' => 'Called the customer']);

        $this->assertRequest(
            'POST',
            'https://api.vrio.app/orders/ord_1/notes',
            '{"body":"Called the customer"}'
        );
    }

    public function testAddOrderNoteRejectsAnEmptyId(): void
    {
        $this->expectException(VrioException::class);

        $this->client()->addOrderNote('');
    }

    public function testConsecutiveCallsDoNotLeakTheEarlierRequestBody(): void
    {
        $client = $this->client();

        $client->addOrder(['email' => 'buyer@example.test']);
        $client->searchOrder(['with' => 'items']);

        $second = $this->http->lastRequest();

        self::assertSame('GET', $second->getMethod());
        self::assertNull($second->getBody());
        self::assertSame('https://api.vrio.app/orders?with=items', $second->getUrl());
        self::assertSame(
            ['endPoint' => 'https://api.vrio.app/orders?with=items', 'with' => 'items'],
            $client->getInArray(true)['payload']
        );
    }
}
