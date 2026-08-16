<?php

declare(strict_types=1);

namespace AsterMD\VrioClient;

use AsterMD\VrioClient\Exception\VrioException;

/**
 * Order endpoints.
 *
 * Several of these carry personal and payment-related data. See the README for
 * how debug logging treats them.
 *
 * @package AsterMD\VrioClient
 */
final class Order extends Vrio
{
    /**
     * Search orders, or list them all when no filter is given.
     *
     * Pass "with" among the query parameters to expand related records.
     *
     * GET /orders
     *
     * @param array<string, mixed> $params Optional query parameters.
     *
     * @return void
     */
    public function searchOrder(array $params = []): void
    {
        $this->section = 'orders';
        $this->sendGet($params);
    }

    /**
     * Retrieve a single order by ID.
     *
     * GET /orders/{orderId}
     *
     * @param string               $orderId
     * @param array<string, mixed> $params  Optional query parameters.
     *
     * @return void
     *
     * @throws VrioException When the order ID is missing.
     */
    public function getOrder(string $orderId, array $params = []): void
    {
        if ($orderId === '') {
            $this->requiredFields();
        }

        $this->section = 'orders/' . $orderId;
        $this->sendGet($params);
    }

    /**
     * Create an order. One order may carry one or many offers or line items.
     *
     * POST /orders
     *
     * @param array<string, mixed> $params Request body fields.
     *
     * @return void
     */
    public function addOrder(array $params = []): void
    {
        $this->section = 'orders';
        $this->fields  = $params;
        $this->sendPost();
    }

    /**
     * Update an existing order.
     *
     * PATCH /orders/{order_id}
     *
     * @param array<string, mixed> $params Request body fields; must include "order_id".
     *
     * @return void
     *
     * @throws VrioException When "order_id" is missing.
     */
    public function editOrder(array $params = []): void
    {
        $orderId = (isset($params['order_id']) && is_scalar($params['order_id']))
            ? (string) $params['order_id']
            : '';

        if ($orderId === '') {
            $this->requiredFields();
        }

        $this->section = 'orders/' . $orderId;
        $this->fields  = $params;
        $this->sendPatch();
    }

    /**
     * Start processing an order.
     *
     * POST /orders/{orderId}/process
     *
     * @param string               $orderId
     * @param array<string, mixed> $params  Request body fields.
     *
     * @return void
     *
     * @throws VrioException When the order ID is missing.
     */
    public function processOrder(string $orderId, array $params = []): void
    {
        if ($orderId === '') {
            $this->requiredFields();
        }

        $this->section = 'orders/' . $orderId . '/process';
        $this->fields  = $params;
        $this->sendPost();
    }

    /**
     * Mark an order complete.
     *
     * POST /orders/{orderId}/complete
     *
     * @param string               $orderId
     * @param array<string, mixed> $params  Request body fields.
     *
     * @return void
     *
     * @throws VrioException When the order ID is missing.
     */
    public function completeOrder(string $orderId, array $params = []): void
    {
        if ($orderId === '') {
            $this->requiredFields();
        }

        $this->section = 'orders/' . $orderId . '/complete';
        $this->fields  = $params;
        $this->sendPost();
    }

    /**
     * Authorize payment for an existing order.
     *
     * POST /orders/{orderId}/authorize
     *
     * @param string               $orderId
     * @param array<string, mixed> $params  Request body fields.
     *
     * @return void
     *
     * @throws VrioException When the order ID is missing.
     */
    public function authorizeOrder(string $orderId, array $params = []): void
    {
        if ($orderId === '') {
            $this->requiredFields();
        }

        $this->section = 'orders/' . $orderId . '/authorize';
        $this->fields  = $params;
        $this->sendPost();
    }

    /**
     * Capture payment for an existing order.
     *
     * POST /orders/{orderId}/capture
     *
     * @param string               $orderId
     * @param array<string, mixed> $params  Request body fields.
     *
     * @return void
     *
     * @throws VrioException When the order ID is missing.
     */
    public function captureOrder(string $orderId, array $params = []): void
    {
        if ($orderId === '') {
            $this->requiredFields();
        }

        $this->section = 'orders/' . $orderId . '/capture';
        $this->fields  = $params;
        $this->sendPost();
    }

    /**
     * Attach a note to an order.
     *
     * POST /orders/{orderId}/notes
     *
     * @param string               $orderId
     * @param array<string, mixed> $params  Request body fields.
     *
     * @return void
     *
     * @throws VrioException When the order ID is missing.
     */
    public function addOrderNote(string $orderId, array $params = []): void
    {
        if ($orderId === '') {
            $this->requiredFields();
        }

        $this->section = 'orders/' . $orderId . '/notes';
        $this->fields  = $params;
        $this->sendPost();
    }
}
