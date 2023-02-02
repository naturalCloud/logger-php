<?php

namespace NaturalCloud\Logger\Formatter;

use Monolog\Formatter\LineFormatter;
use Monolog\Logger;


/**
 * 终端调试日志
 */
class ConsoleColorFormatter extends LineFormatter
{

    public function format(array $record): string
    {
        $output = parent::format($record);
        return $this->colorString(rtrim($output), $record['level']) . "\n";
    }


    private function colorString(string $str, int $logLevel): string
    {
        switch ($logLevel) {
            case Logger::DEBUG: // LOG_LEVEL_DEBUG
                $out = "[46m";
                break;
            case Logger::INFO:
                $out = "[42m";
                break;
            case Logger::NOTICE:
                $out = "[43m";
                break;
            case Logger::WARNING:
                $out = "[45m";
                break;
            case Logger::ERROR:
                $out = "[41m";
                break;
            default:
                $out = "[42m";
                break;
        }
        return chr(27) . "$out" . "{$str}" . chr(27) . "[0m";
    }

}