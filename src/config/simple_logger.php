<?php

use Monolog\Processor\ProcessIdProcessor;

$storage_path = sys_get_temp_dir();

return [

    'storage_path' => $storage_path,// 日志主目录
    'default'      => [
        'handlers'   => [
            [
                'class'       => Monolog\Handler\StreamHandler::class,
                'constructor' => [
                    'stream' => $storage_path . '/simple.log',
                    'level'  => Monolog\Logger::DEBUG,
                ],
                'formatter'   => [
                    'class'       => Monolog\Formatter\LineFormatter::class,
                    'constructor' => [],
                ],
            ],
        ],
        'processors' => [
            [
                'class'       => \NaturalCloud\Logger\Processor\TraceIdProcessor::class,
                'constructor' => [],
            ],
        ],
    ],

    'json' => [
        'handlers'   => [
            [
                'class'       => Monolog\Handler\StreamHandler::class,
                'constructor' => [
                    'stream' => $storage_path . '/json.log',
                    'level'  => Monolog\Logger::DEBUG,
                ],
                'formatter'   => [
                    'class'       => \NaturalCloud\Logger\Formatter\JsonFormatter::class,
                    'constructor' => [],
                ],
            ],
        ],
        'processors' => [
            [
                'class'       => \NaturalCloud\Logger\Processor\TraceIdProcessor::class,
                'constructor' => [],
            ],
            [
                'class'       => ProcessIdProcessor::class,
                'constructor' => [],
            ],
//            [
//                'class' => \Monolog\Processor\GitProcessor::class,
//                'constructor' => []
//            ],
        ],
    ],

    'console' => [
        'handlers'  => [
            [
                'class'       => Monolog\Handler\StreamHandler::class,
                'constructor' => [
                    'stream' => STDOUT,
                    'level'  => Monolog\Logger::DEBUG,
                ],
                'formatter'   => [
                    'class'       => \NaturalCloud\Logger\Formatter\ConsoleColorFormatter::class,
                    'constructor' => [
                        'dateFormat' => 'Y-m-d H:i:s',
                    ],
                ],
            ],
        ],
        'processor' => [
            'class'       => ProcessIdProcessor::class,
            'constructor' => [],
        ],
    ],
];
