<?php

declare(strict_types=1);

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

namespace nova\plugin\task\closure\Signers;

use nova\plugin\task\closure\Contracts\Signer;

class Hmac implements Signer
{
    /**
     * The secret key.
     *
     * @var string
     */
    protected $secret;

    /**
     * Creates a new signer instance.
     *
     * @param  string $secret
     * @return void
     */
    public function __construct($secret)
    {
        $this->secret = $secret;
    }

    /**
     * Sign the given serializable.
     *
     * @param  string $serialized
     * @return array
     */
    public function sign($serialized)
    {
        return [
            'serializable' => $serialized,
            'hash' => base64_encode(hash_hmac('sha256', $serialized, $this->secret, true)),
        ];
    }

    /**
     * Verify the given signature.
     *
     * @param  array $signature
     * @return bool
     */
    public function verify($signature)
    {
        return hash_equals(base64_encode(
            hash_hmac('sha256', $signature['serializable'], $this->secret, true)
        ), $signature['hash']);
    }
}
