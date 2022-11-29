<?php

namespace App\Actions\Tools\Exceptions;

use RuntimeException;

abstract class AbstractCommandExecutionException extends RuntimeException
{
    public function __construct(string $command, int $code, array $output)
    {
        $message = "Command: '{$command}' failed with code '{$code}' and output: " . PHP_EOL . implode(PHP_EOL, $output);
        parent::__construct($message, $code);
    }
}
