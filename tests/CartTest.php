<?php

declare(strict_types=1);

namespace AsterMD\VrioClient\Tests;

use AsterMD\VrioClient\Exception\VrioException;
use AsterMD\VrioClient\Tests\Support\ClientTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\AsterMD\VrioClient\Cart::class)]
final class CartTest extends ClientTestCase
{
    public function testCreateCart(): void
    {
        $this->client()->createCart(['campaign_id' => 'camp_1']);

        $this->assertRequest('POST', 'https://api.vrio.app/carts', '{"campaign_id":"camp_1"}');
    }

    public function testCreatePaypalToken(): void
    {
        $this->client()->createPaypalToken(['cart_token' => 'crt_1', 'return_url' => 'https://example.test/done']);

        $this->assertRequest(
            'POST',
            'https://api.vrio.app/carts/crt_1/payment_tokens',
            '{"cart_token":"crt_1","return_url":"https:\/\/example.test\/done"}'
        );
    }

    public function testCreatePaypalTokenRequiresACartToken(): void
    {
        $this->expectException(VrioException::class);

        $this->client()->createPaypalToken(['return_url' => 'https://example.test/done']);
    }

    public function testGetPaypalData(): void
    {
        $this->client()->getPaypalData(['cart_token' => 'crt_1', 'payment_token_id' => 'pt_2']);

        $this->assertRequest('GET', 'https://api.vrio.app/carts/crt_1/payment_tokens/pt_2');
    }

    public function testGetPaypalDataRequiresBothIdentifiers(): void
    {
        $this->expectException(VrioException::class);

        $this->client()->getPaypalData(['cart_token' => 'crt_1']);
    }
}
