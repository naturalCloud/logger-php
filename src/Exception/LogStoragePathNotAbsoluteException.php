<?php

namespace NaturalCloud\Logger\Exception;

use Throwable;

class LogStoragePathNotAbsoluteException extends \RuntimeException
{

    public function __construct(
        $message = "log config [storage_path] must is absolute ",
        $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}