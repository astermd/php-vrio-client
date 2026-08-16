<?php

declare(strict_types=1);

namespace AsterMD\VrioClient\Tests;

use AsterMD\VrioClient\Tests\Support\ClientTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\AsterMD\VrioClient\Offer::class)]
final class OfferTest extends ClientTestCase
{
    public function testSearchOfferWithoutParameters(): void
    {
        $this->client()->searchOffer();

        $this->assertRequest('GET', 'https://api.vrio.app/offers');
    }

    public function testSearchOfferWithParameters(): void
    {
        $this->client()->searchOffer(['campaign_id' => 'camp_1']);

        $this->assertRequest('GET', 'https://api.vrio.app/offers?campaign_id=camp_1');
    }
}
