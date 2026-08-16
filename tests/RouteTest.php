<?php

declare(strict_types=1);

namespace AsterMD\VrioClient\Tests;

use AsterMD\VrioClient\Exception\VrioException;
use AsterMD\VrioClient\Tests\Support\ClientTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\AsterMD\VrioClient\Route::class)]
final class RouteTest extends ClientTestCase
{
    public function testGetRoute(): void
    {
        $this->client()->getRoute('rt_5');

        $this->assertRequest('GET', 'https://api.vrio.app/routes/rt_5');
    }

    public function testGetRouteWithQueryParameters(): void
    {
        $this->client()->getRoute('rt_5', ['with' => 'offers']);

        $this->assertRequest('GET', 'https://api.vrio.app/routes/rt_5?with=offers');
    }

    public function testGetRouteRejectsAnEmptyId(): void
    {
        $this->expectException(VrioException::class);

        $this->client()->getRoute('');
    }
}
