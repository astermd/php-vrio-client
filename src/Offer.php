<?php

declare(strict_types=1);

namespace AsterMD\VrioClient;

/**
 * Offer endpoints.
 *
 * @package AsterMD\VrioClient
 */
final class Offer extends Vrio
{
    /**
     * Search offers.
     *
     * GET /offers
     *
     * @param array<string, mixed> $params Optional query parameters.
     *
     * @return void
     */
    public function searchOffer(array $params = []): void
    {
        $this->section = 'offers';
        $this->sendGet($params);
    }
}
