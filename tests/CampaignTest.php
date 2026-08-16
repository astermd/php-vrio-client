<?php

declare(strict_types=1);

namespace AsterMD\VrioClient\Tests;

use AsterMD\VrioClient\Exception\VrioException;
use AsterMD\VrioClient\Tests\Support\ClientTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\AsterMD\VrioClient\Campaign::class)]
final class CampaignTest extends ClientTestCase
{
    public function testGetCampaignItemsWithoutParameters(): void
    {
        $this->client()->getCampaignItems('camp_1');

        $this->assertRequest('GET', 'https://api.vrio.app/campaigns/camp_1/items');
    }

    public function testGetCampaignItemsWithQueryParameters(): void
    {
        $this->client()->getCampaignItems('camp_1', ['with' => 'offers', 'limit' => 25]);

        $this->assertRequest('GET', 'https://api.vrio.app/campaigns/camp_1/items?with=offers&limit=25');
    }

    public function testGetCampaignItemsRejectsAnEmptyCampaignId(): void
    {
        $this->expectException(VrioException::class);
        $this->expectExceptionMessage('Missing required fields');

        $this->client()->getCampaignItems('');
    }
}
