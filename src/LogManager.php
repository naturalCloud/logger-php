<?php

namespace NaturalCloud\Logger;

use Closure;
use DI\Container;
use InvalidArgumentException;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Handler\WhatFailureGroupHandler;
use Monolog\Logger as Monolog;
use NaturalCloud\Logger\Exception\LogStoragePathNotAbsoluteException;
use NaturalCloud\Logger\Support\Arr;
use NaturalCloud\Logger\Support\Str;
use NaturalCloud\Logger\Tap\TapFormatter;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use function  NaturalCloud\Logger\Support\with;
use function  NaturalCloud\Logger\Support\tap;


class LogManager implements LoggerInterface
{
    use ParsesLogConfiguration;

    protected $cfg;

    /**
     * The array of resolved channels.
     *
     * @var array
     */
    protected $channels = [];

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected $customCreators = [];

    /**
     * The standard date format to use when writing logs.
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Create a new Log manager instance.
     *
     * @param array $config
     */


    protected $app;

    public function __construct(array $config = [], ContainerInterface $di = null)
    {
        $this->cfg = ['logging' => $config];
        if ($di instanceof ContainerInterface && method_exists($di, 'make')) {
            $this->app = $di;
        } else {
            $this->app = new Container();
        }

    }

    /**
     * Build an on-demand log channel.
     *
     * @param array $config
     * @return \Psr\Log\LoggerInterface
     */
    public function build(array $config)
    {
        unset($this->channels['ondemand']);

        return $this->get('ondemand', $config);
    }

    /**
     * Create a new, on-demand aggregate logger instance.
     *
     * @param array $channels
     * @param string|null $channel
     * @return \Psr\Log\LoggerInterface
     */
    public function stack(array $channels, $channel = null)
    {
        return new Logger(
            $this->createStackDriver(compact('channels', 'channel'))
        );
    }

    /**
     * Get a log channel instance.
     *
     * @param string|null $channel
     * @return \Psr\Log\LoggerInterface
     */
    public function channel($channel = null)
    {
        return $this->driver($channel);
    }

    /**
     * Get a log driver instance.
     *
     * @param string|null $driver
     * @return \Psr\Log\LoggerInterface
     */
    public function driver($driver = null)
    {
        return $this->get($this->parseDriver($driver));
    }

    /**
     * Attempt to get the log from the local cache.
     *
     * @param string $name
     * @param array|null $config
     * @return \Psr\Log\LoggerInterface
     */
    protected function get($name, ?array $config = null)
    {
        try {
            return $this->channels[$name] ?? with($this->resolve($name, $config),
                function ($logger) use ($name, $config) {
                    return $this->channels[$name] = $this->tap($name, new Logger($logger), $config);
                });
        } catch (Throwable $e) {
            return tap($this->createEmergencyLogger(), function ($logger) use ($e) {
                $logger->emergency('Unable to create configured logger. Using emergency logger.', [
                    'exception' => $e,
                ]);
            });
        }
    }

    /**
     * Apply the configured taps for the logger.
     *
     * @param string $name
     * @param Logger $logger
     * @return Logger
     */
    protected function tap($name, Logger $logger, $config = [])
    {
        $driveConfigTap = $this->configurationFor($name)['tap'] ?? [];
        $configTap = $config['tap'] ?? [];
        $taps = array_unique(array_merge($configTap, $driveConfigTap));
        foreach ($taps as $tap) {
            [$class, $arguments] = $this->parseTap($tap);

            $this->app->make($class)->__invoke($logger, ...explode(',', $arguments));
        }

        return $logger;
    }

    /**
     * Parse the given tap class string into a class name and arguments string.
     *
     * @param string $tap
     * @return array
     */
    protected function parseTap($tap)
    {
        return Str::contains($tap, ':') ? explode(':', $tap, 2) : [$tap, ''];
    }

    /**
     * Create an emergency log handler to avoid white screens of death.
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function createEmergencyLogger()
    {
        $config = $this->configurationFor('emergency');

        $handler = new StreamHandler(
            Str::pathIsAbsolute($config['path']) ? $config['path'] : $this->parseLogFilePath($config['path']),
            $this->level(['level' => 'debug'])
        );

        return new Logger(
            new Monolog('emergency', $this->prepareHandlers([$handler]))
        );
    }

    /**
     * Resolve the given log instance by name.
     *
     * @param string $name
     * @param array|null $config
     * @return \Psr\Log\LoggerInterface
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name, ?array $config = null)
    {
        $config = $config ?? $this->configurationFor($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Log [{$name}] is not defined.");
        }

        $logFilePath = Arr::get($config, 'path', '');
        if ($logFilePath && !Str::pathIsAbsolute($logFilePath)) {
            $config['path'] = $this->parseLogFilePath($logFilePath, $config);
        }

        if (isset($this->customCreators[$config['driver']])) {
            return $this->callCustomCreator($config);
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($config);
        }

        throw new InvalidArgumentException("Driver [{$config['driver']}] is not supported.");
    }

    /**
     * Call a custom driver creator.
     *
     * @param array $config
     * @return mixed
     */
    protected function callCustomCreator(array $config)
    {
        return $this->customCreators[$config['driver']]($this->app, $config);
    }

    /**
     * Create a custom log driver instance.
     *
     * @param array $config
     * @return \Psr\Log\LoggerInterface
     */
    protected function createCustomDriver(array $config)
    {
        $factory = is_callable($via = $config['via']) ? $via : $this->app->make($via);

        return $factory($config);
    }

    /**
     * Create an aggregate log driver instance.
     *
     * @param array $config
     * @return \Psr\Log\LoggerInterface
     */
    protected function createStackDriver(array $config)
    {
        if (is_string($config['channels'])) {
            $config['channels'] = explode(',', $config['channels']);
        }

        $handlers = Arr::flatMap($config['channels'], function ($channel) {
            return $channel instanceof LoggerInterface
                ? $channel->getHandlers()
                : $this->channel($channel)->getHandlers();
        });

        $processors = Arr::flatMap($config['channels'], function ($channel) {
            return $channel instanceof LoggerInterface
                ? $channel->getProcessors()
                : $this->channel($channel)->getProcessors();
        });
        if ($config['ignore_exceptions'] ?? false) {
            $handlers = [new WhatFailureGroupHandler($handlers)];
        }

        return new Monolog($this->parseChannel($config), $handlers, $processors);
    }

    /**
     * Create an instance of the single file log driver.
     *
     * @param array $config
     * @return \Psr\Log\LoggerInterface
     */
    protected function createSingleDriver(array $config)
    {
        return new Monolog($this->parseChannel($config), [
            $this->prepareHandler(
                new StreamHandler(
                    $config['path'], $this->level($config),
                    $config['bubble'] ?? true, $config['permission'] ?? null, $config['locking'] ?? false
                ), $config
            ),
        ]);
    }

    /**
     * Create an instance of the daily file log driver.
     *
     * @param array $config
     * @return \Psr\Log\LoggerInterface
     */
    protected function createDailyDriver(array $config)
    {
        return new Monolog($this->parseChannel($config), [
            $this->prepareHandler(new RotatingFileHandler(
                $config['path'], $config['days'] ?? 7, $this->level($config),
                $config['bubble'] ?? true, $config['permission'] ?? null, $config['locking'] ?? false
            ), $config),
        ]);
    }

    /**
     * Create an instance of the Slack log driver.
     *
     * @param array $config
     * @return \Psr\Log\LoggerInterface
     */
    protected function createSlackDriver(array $config)
    {
        return new Monolog($this->parseChannel($config), [
            $this->prepareHandler(new SlackWebhookHandler(
                $config['url'],
                $config['channel'] ?? null,
                $config['username'] ?? 'Laravel',
                $config['attachment'] ?? true,
                $config['emoji'] ?? ':boom:',
                $config['short'] ?? false,
                $config['context'] ?? true,
                $this->level($config),
                $config['bubble'] ?? true,
                $config['exclude_fields'] ?? []
            ), $config),
        ]);
    }

    /**
     * Create an instance of the syslog log driver.
     *
     * @param array $config
     * @return \Psr\Log\LoggerInterface
     */
    protected function createSyslogDriver(array $config)
    {
        return new Monolog($this->parseChannel($config), [
            $this->prepareHandler(new SyslogHandler(
                Str::snake(Arr::get($this->cfg, 'projectName', 'php-logger'), '-'),
                $config['facility'] ?? LOG_USER, $this->level($config)
            ), $config),
        ]);
    }

    /**
     * Create an instance of the "error log" log driver.
     *
     * @param array $config
     * @return \Psr\Log\LoggerInterface
     */
    protected function createErrorlogDriver(array $config)
    {
        return new Monolog($this->parseChannel($config), [
            $this->prepareHandler(new ErrorLogHandler(
                $config['type'] ?? ErrorLogHandler::OPERATING_SYSTEM, $this->level($config)
            )),
        ]);
    }

    /**
     * Create an instance of any handler available in Monolog.
     *
     * @param array $config
     * @return \Psr\Log\LoggerInterface
     *
     * @throws \InvalidArgumentException
     */
    protected function createMonologDriver(array $config)
    {
        if (!is_a($config['handler'], HandlerInterface::class, true)) {
            throw new InvalidArgumentException(
                $config['handler'] . ' must be an instance of ' . HandlerInterface::class
            );
        }

        $with = array_merge(
            ['level' => $this->level($config)],
            $config['with'] ?? [],
            $config['handler_with'] ?? []
        );

        return new Monolog($this->parseChannel($config), [
            $this->prepareHandler(
                $this->app->make($config['handler'], $with), $config
            ),
        ]);
    }

    /**
     * Prepare the handlers for usage by Monolog.
     *
     * @param array $handlers
     * @return array
     */
    protected function prepareHandlers(array $handlers)
    {
        foreach ($handlers as $key => $handler) {
            $handlers[$key] = $this->prepareHandler($handler);
        }

        return $handlers;
    }

    /**
     * Prepare the handler for usage by Monolog.
     *
     * @param \Monolog\Handler\HandlerInterface $handler
     * @param array $config
     * @return \Monolog\Handler\HandlerInterface
     */
    protected function prepareHandler(HandlerInterface $handler, array $config = [])
    {
        if (isset($config['action_level'])) {
            $handler = new FingersCrossedHandler($handler, $this->actionLevel($config));
        }

        if (Monolog::API !== 1 && (Monolog::API !== 2 || !$handler instanceof FormattableHandlerInterface)) {
            return $handler;
        }

        if (!isset($config['formatter'])) {
            $handler->setFormatter($this->formatter());
        } elseif ($config['formatter'] !== 'default') {
            $handler->setFormatter($this->app->make($config['formatter'], $config['formatter_with'] ?? []));
        }

        return $handler;
    }

    /**
     * Get a Monolog formatter instance.
     *
     * @return \Monolog\Formatter\FormatterInterface
     */
    protected function formatter()
    {
        return tap(new LineFormatter(null, $this->dateFormat, true, true), function ($formatter) {
            $formatter->includeStacktraces();
        });
    }

    /**
     * Get fallback log channel name.
     *
     * @return string
     */
    protected function getFallbackChannelName()
    {
        return 'php-logger';
    }

    /**
     * Get the log connection configuration.
     *
     * @param string $name
     * @return array
     */
    protected function configurationFor($name)
    {
        return Arr::get($this->cfg, "logging.channels.{$name}");
    }

    /**
     * Get the default log driver name.
     *
     * @return string|null
     */
    public function getDefaultDriver()
    {
        return Arr::get($this->cfg, 'logging.default');
    }

    /**
     * Set the default log driver name.
     *
     * @param string $name
     * @return void
     */
    public function setDefaultDriver($name)
    {
        Arr::set($this->cfg, 'logging.default', $name);
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param string $driver
     * @param \Closure $callback
     * @return $this
     */
    public function extend($driver, Closure $callback)
    {
        $this->customCreators[$driver] = $callback->bindTo($this, $this);

        return $this;
    }

    /**
     * Unset the given channel instance.
     *
     * @param string|null $driver
     * @return $this
     */
    public function forgetChannel($driver = null)
    {
        $driver = $this->parseDriver($driver);

        if (isset($this->channels[$driver])) {
            unset($this->channels[$driver]);
        }
    }

    /**
     * Parse the driver name.
     *
     * @param string|null $driver
     * @return string|null
     */
    protected function parseDriver($driver)
    {
        $driver = $driver ?? $this->getDefaultDriver();

        return $driver;
    }

    /**
     * Get all of the resolved log channels.
     *
     * @return array
     */
    public function getChannels()
    {
        return $this->channels;
    }

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function emergency($message, array $context = [])
    {
        $this->driver()->emergency($message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function alert($message, array $context = [])
    {
        $this->driver()->alert($message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function critical($message, array $context = [])
    {
        $this->driver()->critical($message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error($message, array $context = [])
    {
        $this->driver()->error($message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warning($message, array $context = [])
    {
        $this->driver()->warning($message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function notice($message, array $context = [])
    {
        $this->driver()->notice($message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info($message, array $context = [])
    {
        $this->driver()->info($message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debug($message, array $context = [])
    {
        $this->driver()->debug($message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        $this->driver()->log($level, $message, $context);
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }


    /**
     * @param $logName string 文件名
     * @param $config [] 配置文件
     * @return LoggerInterface
     */
    public function channelFile($logName, $config = [])
    {

        $baseLogPath = $this->configGet('storage_path', '');
        if (!Str::pathIsAbsolute($baseLogPath)) {
            throw new LogStoragePathNotAbsoluteException();
        }
        return $this->get($logName,
            array_merge([
                'name'    => $logName,
                'driver'  => 'monolog',
                'handler' => StreamHandler::class,
                'with'    => [
                    'stream' => 'file://' . rtrim($baseLogPath, '/') . "/$logName.log",
                ],
                'level'   => 'debug',
                'tap'     => [TapFormatter::class,],
            ], $config));
    }


    /**
     * 获取当前 日志 config
     * @param $key
     * @param $default
     * @return array|\ArrayAccess|mixed
     */
    protected function configGet($key, $default = null)
    {
        return Arr::get($this->cfg, "logging.$key", $default);
    }


    /**
     * @param $logFilePath
     * @param array $config
     * @return string
     */
    protected function parseLogFilePath($logFilePath, array $config = [])
    {
        $storagePath = $this->configGet('storage_path', '');
        if (!Str::pathIsAbsolute($storagePath)) {
            throw new LogStoragePathNotAbsoluteException();
        }
        return rtrim($storagePath, '/') . '/' . ltrim($logFilePath, '/');
    }
}
