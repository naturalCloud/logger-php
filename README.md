# logger

## 介绍

借鉴和迁移修改 `laravel` 和 `hyperf`  对 `monolog/monolog` 封装,尽量达到快速接入任一 `php` 框架

## 接入

### easyswoole

#### 复制 `config` 到 `dev.php`

```php
storage_path = sys_get_temp_dir();
return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */
    // 'default' => env('LOG_CHANNEL', 'stack'),
    'default' => 'stack',

    'storage_path' => $storage_path,// 日志主目录,

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => 'LOG_DEPRECATIONS_CHANNEL',

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => 'single.log', // 日志文件路径 ,绝对路径, storage_path 设置可设置相对路径
            'level' => 'debug',
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => 'daily.log', // 日志文件路径 ,绝对路径,  storage_path 设置可设置相对路径
            'level' => 'debug',
            'days' => 14,
        ],
        'syslog' => [
            'driver' => 'syslog',
            'level' => 'debug',
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => 'debug',
        ],

        'emergency' => [
            'driver' => 'single',
            'path' => 'emergency.log', // 日志文件路径 ,绝对路径,  storage_path 设置可设置相对路径
        ],
        // 终端打印日志
        'console' => [
            'name' => 'console', // 显示的名字
            'driver' => 'monolog',
            'level' => 'debug',
            'handler' => StreamHandler::class,
            'formatter' => \NaturalCloud\Logger\Formatter\ConsoleColorFormatter::class,
            'with' => [
                // handler 参数
                'stream' => STDOUT,
            ],
            'formatter_with' => [ // formatter 的构造函数参数
                'dateFormat' => 'Y-m-d H:i:s'
            ],
            'tap' => [\NaturalCloud\Logger\Tap\TapFormatter::class]
        ],
        'json' => [
            'driver' => 'monolog',
            'level' => 'debug',
            'handler' => StreamHandler::class,
            'with' => [ // handler 参数
                'stream' => $storage_path . '/json.log',
            ],
            'formatter' => \NaturalCloud\Logger\Formatter\JsonFormatter::class,
            'formatter_with' => [ // formatter 的构造函数参数
                'dateFormat' => 'Y-m-d H:i:s'
            ],
            'tap' => [\NaturalCloud\Logger\Tap\TapFormatter::class]
        ]
    ],

];
```

#### 注入 `Di` 容器

```php
$loggerConfig = [];
\NaturalCloud\Logger\Framework\Easyswoole::setLogger($loggerConfig,'logger-new')
```

#### 获取 `logger`

```php
$logger = \NaturalCloud\Logger\Framework\Easyswoole::getLogger('logger-new');
// 写到默认 chanel 
$logger->info('aaaa');
$logger->warning('bbbb');
// 打印到终端 info 级别
$logger->channel('console')->info('in console');
// 快速自定义配置logger ，用于动态的写入不同文件名的logger
// 写入 日志主目录/swoole.log
$logger->channelFile('swoole'); 
```

#### 设置 `traceId`

```php
\NaturalCloud\Logger\Framework\Easyswoole::trace($request,$response,'x-request-id',function (){
return \NaturalCloud\Logger\Support\Str::random('36')
});
```