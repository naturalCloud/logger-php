<?php

namespace NaturalCloud\Logger\Formatter;

class JsonFormatter extends \Monolog\Formatter\JsonFormatter
{
    public const SIMPLE_DATE = 'Y-m-d H:i:s';

    public function __construct(
        string $dateFormat = 'Y-m-d H:i:s',
        int $batchMode = \Monolog\Formatter\JsonFormatter::BATCH_MODE_JSON,
        bool $appendNewline = true,
        bool $ignoreEmptyContextAndExtra = false,
        bool $includeStacktraces = false
    ) {
        \Monolog\Formatter\JsonFormatter::__construct($batchMode, $appendNewline, $ignoreEmptyContextAndExtra,
            $includeStacktraces);

        if ($dateFormat) {
            $this->dateFormat = $dateFormat;
        }

    }
}