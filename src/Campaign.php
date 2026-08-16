<?php

declare(strict_types=1);

namespace AsterMD\VrioClient;

use AsterMD\VrioClient\Exception\VrioException;

/**
 * Campaign endpoints.
 *
 * @package AsterMD\VrioClient
 */
final class Campaign extends Vrio
{
    /**
     * Return every item belonging to a campaign.
     *
     * GET /campaigns/{campaignId}/items
     *
     * @param string               $campaignId
     * @param array<string, mixed> $params     Optional query parameters.
     *
     * @return void
     *
     * @throws VrioException When the campaign ID is missing.
     */
    public function getCampaignItems(string $campaignId, array $params = []): void
    {
        if ($campaignId === '') {
            $this->requiredFields();
        }

        $this->section = 'campaigns/' . $campaignId . '/items';
        $this->sendGet($params);
    }
}
