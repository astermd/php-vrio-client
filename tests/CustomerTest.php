<?php

declare(strict_types=1);

namespace AsterMD\VrioClient\Tests;

use AsterMD\VrioClient\Exception\VrioException;
use AsterMD\VrioClient\Tests\Support\ClientTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\AsterMD\VrioClient\Customer::class)]
final class CustomerTest extends ClientTestCase
{
    public function testGetCustomer(): void
    {
        $this->client()->getCustomer('cus_9');

        $this->assertRequest('GET', 'https://api.vrio.app/customers/cus_9');
    }

    public function testGetCustomerWithQueryParameters(): void
    {
        $this->client()->getCustomer('cus_9', ['with' => 'orders']);

        $this->assertRequest('GET', 'https://api.vrio.app/customers/cus_9?with=orders');
    }

    public function testGetCustomerRejectsAnEmptyId(): void
    {
        $this->expectException(VrioException::class);

        $this->client()->getCustomer('');
    }
}
