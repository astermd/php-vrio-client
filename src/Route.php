<?php

declare(strict_types=1);

namespace AsterMD\VrioClient;

use AsterMD\VrioClient\Exception\VrioException;

/**
 * Route endpoints.
 *
 * @package AsterMD\VrioClient
 */
final class Route extends Vrio
{
    /**
     * Retrieve a route by ID.
     *
     * GET /routes/{routeId}
     *
     * @param string               $routeId
     * @param array<string, mixed> $params  Optional query parameters.
     *
     * @return void
     *
     * @throws VrioException When the route ID is missing.
     */
    public function getRoute(string $routeId, array $params = []): void
    {
        if ($routeId === '') {
            $this->requiredFields();
        }

        $this->section = 'routes/' . $routeId;
        $this->sendGet($params);
    }
}
