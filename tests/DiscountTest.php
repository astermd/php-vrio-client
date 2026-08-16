<?php

declare(strict_types=1);

namespace AsterMD\VrioClient\Tests;

use AsterMD\VrioClient\Tests\Support\ClientTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\AsterMD\VrioClient\Discount::class)]
final class DiscountTest extends ClientTestCase
{
    public function testValidateDiscountPostsTheGivenBody(): void
    {
        $this->client()->validateDiscount(['code' => 'SAVE10', 'offer_id' => 'off_1']);

        $this->assertRequest(
            'POST',
            'https://api.vrio.app/discounts/validate',
            '{"code":"SAVE10","offer_id":"off_1"}'
        );
    }

    public function testCalculateDiscountWrapsTheParametersInAnOffersMember(): void
    {
        $this->client()->calculateDiscount(['off_1' => ['quantity' => 2]]);

        $this->assertRequest(
            'POST',
            'https://api.vrio.app/discounts/calculate',
            '{"offers":{"off_1":{"quantity":2}}}'
        );
    }
}
