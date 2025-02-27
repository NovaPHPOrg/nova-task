<?php
/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

namespace nova\plugin\task\closure;

use Closure;
use nova\plugin\task\closure\Contracts\Serializable;
use nova\plugin\task\closure\Exceptions\InvalidSignatureException;
use nova\plugin\task\closure\Exceptions\PhpVersionNotSupportedException;
use nova\plugin\task\closure\Serializers\Signed;
use nova\plugin\task\closure\Signers\Hmac;
use const PHP_VERSION_ID;

class SerializableClosure
{
    /**
     * The closure's serializable.
     *
     * @var Serializable
     */
    protected $serializable;

    /**
     * Creates a new serializable closure instance.
     *
     * @param Closure $closure
     * @return void
     */
    public function __construct(Closure $closure)
    {
        if (PHP_VERSION_ID < 70400) {
            throw new PhpVersionNotSupportedException();
        }

        $this->serializable = Serializers\Signed::$signer
            ? new Serializers\Signed($closure)
            : new Serializers\Native($closure);
    }

    /**
     * Resolve the closure with the given arguments.
     *
     * @return mixed
     */
    public function __invoke()
    {
        if (PHP_VERSION_ID < 70400) {
            throw new PhpVersionNotSupportedException();
        }

        return call_user_func_array($this->serializable, func_get_args());
    }

    /**
     * Gets the closure.
     *
     * @return Closure
     */
    public function getClosure()
    {
        if (PHP_VERSION_ID < 70400) {
            throw new PhpVersionNotSupportedException();
        }

        return $this->serializable->getClosure();
    }

    /**
     * Create a new unsigned serializable closure instance.
     *
     * @param  Closure  $closure
     * @return UnsignedSerializableClosure
     */
    public static function unsigned(Closure $closure)
    {
        return new UnsignedSerializableClosure($closure);
    }

    /**
     * Sets the serializable closure secret key.
     *
     * @param  string|null  $secret
     * @return void
     */
    public static function setSecretKey($secret)
    {
        Serializers\Signed::$signer = $secret
            ? new Hmac($secret)
            : null;
    }

    /**
     * Sets the serializable closure secret key.
     *
     * @param Closure|null $transformer
     * @return void
     */
    public static function transformUseVariablesUsing($transformer)
    {
        Serializers\Native::$transformUseVariables = $transformer;
    }

    /**
     * Sets the serializable closure secret key.
     *
     * @param Closure|null $resolver
     * @return void
     */
    public static function resolveUseVariablesUsing($resolver)
    {
        Serializers\Native::$resolveUseVariables = $resolver;
    }

    /**
     * Get the serializable representation of the closure.
     *
     * @return array
     */
    public function __serialize()
    {
        return [
            'serializable' => $this->serializable,
        ];
    }

    /**
     * Restore the closure after serialization.
     *
     * @param  array  $data
     * @return void
     *
     * @throws InvalidSignatureException
     */
    public function __unserialize($data)
    {
        if (Signed::$signer && ! $data['serializable'] instanceof Signed) {
            throw new InvalidSignatureException();
        }

        $this->serializable = $data['serializable'];
    }
}
