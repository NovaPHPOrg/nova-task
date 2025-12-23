<?php

declare(strict_types=1);

namespace nova\plugin\task\closure\Contracts;

interface Serializable
{
    /**
     * Resolve the closure with the given arguments.
     *
     * @return mixed
     */
    public function __invoke();

    /**
     * Gets the closure that got serialized/unserialized.
     *
     * @return \Closure
     */
    public function getClosure();
}
