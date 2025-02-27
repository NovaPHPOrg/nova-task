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
use nova\plugin\task\closure\Exceptions\PhpVersionNotSupportedException;
use const PHP_VERSION_ID;

class UnsignedSerializableClosure
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

        $this->serializable = new Serializers\Native($closure);
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
     */
    public function __unserialize($data)
    {
        $this->serializable = $data['serializable'];
    }
}
