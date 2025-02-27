<?php

declare(strict_types=1);

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

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
