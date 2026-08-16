<?php

declare(strict_types=1);

namespace AsterMD\VrioClient;

use AsterMD\VrioClient\Exception\VrioException;

/**
 * Customer endpoints.
 *
 * @package AsterMD\VrioClient
 */
final class Customer extends Vrio
{
    /**
     * Retrieve a customer by ID.
     *
     * GET /customers/{customerId}
     *
     * @param string               $customerId
     * @param array<string, mixed> $params     Optional query parameters.
     *
     * @return void
     *
     * @throws VrioException When the customer ID is missing.
     */
    public function getCustomer(string $customerId, array $params = []): void
    {
        if ($customerId === '') {
            $this->requiredFields();
        }

        $this->section = 'customers/' . $customerId;
        $this->sendGet($params);
    }
}
