<?php

declare(strict_types=1);

namespace AsterMD\VrioClient;

/**
 * Discount endpoints.
 *
 * @package AsterMD\VrioClient
 */
final class Discount extends Vrio
{
    /**
     * Check whether a discount applies to a given offer.
     *
     * POST /discounts/validate
     *
     * @param array<string, mixed> $params Request body fields.
     *
     * @return void
     */
    public function validateDiscount(array $params = []): void
    {
        $this->section = 'discounts/validate';
        $this->fields  = $params;
        $this->sendPost();
    }

    /**
     * Calculate the discount for a set of offers.
     *
     * POST /discounts/calculate
     *
     * The supplied array is sent as the body's "offers" member.
     *
     * @param array<string, mixed> $params Offers to price.
     *
     * @return void
     */
    public function calculateDiscount(array $params = []): void
    {
        $this->section = 'discounts/calculate';
        $this->fields  = ['offers' => $params];
        $this->sendPost();
    }
}
