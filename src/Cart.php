<?php

declare(strict_types=1);

namespace AsterMD\VrioClient;

use AsterMD\VrioClient\Exception\VrioException;

/**
 * Cart and payment-token endpoints.
 *
 * Cart and payment tokens appear in the request path. Because debug logging
 * records the URL verbatim, those identifiers are written to the log even when
 * redaction is on — see the README.
 *
 * @package AsterMD\VrioClient
 */
final class Cart extends Vrio
{
    /**
     * Create a cart.
     *
     * POST /carts
     *
     * @param array<string, mixed> $params Request body fields.
     *
     * @return void
     */
    public function createCart(array $params = []): void
    {
        $this->section = 'carts';
        $this->fields  = $params;
        $this->sendPost();
    }

    /**
     * Create a PayPal payment token for a cart.
     *
     * POST /carts/{cart_token}/payment_tokens
     *
     * @param array<string, mixed> $params Request body fields; must include "cart_token".
     *
     * @return void
     *
     * @throws VrioException When "cart_token" is missing.
     */
    public function createPaypalToken(array $params = []): void
    {
        $cartToken = self::identifier($params, 'cart_token');
        if ($cartToken === '') {
            $this->requiredFields();
        }

        $this->section = 'carts/' . $cartToken . '/payment_tokens';
        $this->fields  = $params;
        $this->sendPost();
    }

    /**
     * Retrieve a PayPal payment token for a cart.
     *
     * GET /carts/{cart_token}/payment_tokens/{payment_token_id}
     *
     * @param array<string, mixed> $params Must include "cart_token" and "payment_token_id".
     *
     * @return void
     *
     * @throws VrioException When either identifier is missing.
     */
    public function getPaypalData(array $params = []): void
    {
        $cartToken      = self::identifier($params, 'cart_token');
        $paymentTokenId = self::identifier($params, 'payment_token_id');

        if ($cartToken === '' || $paymentTokenId === '') {
            $this->requiredFields();
        }

        $this->section = 'carts/' . $cartToken . '/payment_tokens/' . $paymentTokenId;
        $this->sendGet();
    }

    /**
     * Read a path identifier out of the parameter array.
     *
     * @param array<string, mixed> $params
     * @param string               $key
     *
     * @return string Empty when absent or not scalar.
     */
    private static function identifier(array $params, string $key): string
    {
        if (!isset($params[$key]) || !is_scalar($params[$key])) {
            return '';
        }

        return (string) $params[$key];
    }
}
