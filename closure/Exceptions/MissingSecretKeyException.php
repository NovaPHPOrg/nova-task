<?php

declare(strict_types=1);

namespace nova\plugin\task\closure\Exceptions;

use Exception;

class MissingSecretKeyException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param  string $message
     * @return void
     */
    public function __construct($message = 'No serializable closure secret key has been specified.')
    {
        parent::__construct($message);
    }
}
