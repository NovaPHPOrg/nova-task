<?php
/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

namespace nova\plugin\task\closure\Serializers;

use Closure;
use nova\plugin\task\closure\Contracts\Serializable;
use nova\plugin\task\closure\Contracts\Signer;
use nova\plugin\task\closure\Exceptions\InvalidSignatureException;
use nova\plugin\task\closure\Exceptions\MissingSecretKeyException;

class Signed implements Serializable
{
    /**
     * The signer that will sign and verify the closure's signature.
     *
     * @var Signer|null
     */
    public static $signer;

    /**
     * The closure to be serialized/unserialized.
     *
     * @var Closure
     */
    protected $closure;

    /**
     * Creates a new serializable closure instance.
     *
     * @param Closure $closure
     * @return void
     */
    public function __construct($closure)
    {
        $this->closure = $closure;
    }

    /**
     * Resolve the closure with the given arguments.
     *
     * @return mixed
     */
    public function __invoke()
    {
        return call_user_func_array($this->closure, func_get_args());
    }

    /**
     * Gets the closure.
     *
     * @return Closure
     */
    public function getClosure()
    {
        return $this->closure;
    }

    /**
     * Get the serializable representation of the closure.
     *
     * @return array
     */
    public function __serialize()
    {
        if (! static::$signer) {
            throw new MissingSecretKeyException();
        }

        return static::$signer->sign(
            serialize(new Native($this->closure))
        );
    }

    /**
     * Restore the closure after serialization.
     *
     * @param  array  $signature
     * @return void
     *
     * @throws InvalidSignatureException
     */
    public function __unserialize($signature)
    {
        if (static::$signer && ! static::$signer->verify($signature)) {
            throw new InvalidSignatureException();
        }

        /** @var Serializable $serializable */
        $serializable = unserialize($signature['serializable']);

        $this->closure = $serializable->getClosure();
    }
}
