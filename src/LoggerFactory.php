<?php


namespace NaturalCloud\Logger;

use DI\Container;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\ProcessIdProcessor;
use NaturalCloud\Logger\Exception\InvalidConfigException;
use NaturalCloud\Logger\Processor\TraceIdProcessor;
use NaturalCloud\Logger\Support\Arr;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use NaturalCloud\Logger\SimpleLogger as Logger;

class LoggerFactory
{
    /**
     * @var $cfg array|null
     */
    protected $cfg;

    /**
     * @var array
     */
    protected $loggers;

    /**
     * @var Container|ContainerInterface
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

    public function make($name = 'simple', $group = 'default'): LoggerInterface
    {
        $config = $this->configGet($group, null);
        if (!$config) {
            throw new InvalidConfigException(sprintf('Logger config[%s] is not defined.', $name));
        }

        $handlers = $this->handlers($config);
        $processors = $this->processors($config);

        return $this->app->make(Logger::class, [
            'name'       => $name,
            'handlers'   => $handlers,
            'processors' => $processors,
        ]);
    }

    public function get($name = 'simple', $group = 'default'): LoggerInterface
    {
        if (isset($this->loggers[$group][$name]) && $this->loggers[$group][$name] instanceof Logger) {
            return $this->loggers[$group][$name];
        }

        return $this->loggers[$group][$name] = $this->make($name, $group);
    }

    protected function getDefaultFormatterConfig($config)
    {
        $formatterClass = Arr::get($config, 'formatter.class', LineFormatter::class);
        $formatterConstructor = Arr::get($config, 'formatter.constructor', []);

        return [
            'class'       => $formatterClass,
            'constructor' => $formatterConstructor,
        ];
    }

    protected function getDefaultHandlerConfig($config)
    {
        $handlerClass = Arr::get($config, 'handler.class', StreamHandler::class);
        $handlerConstructor = Arr::get($config, 'handler.constructor', [
            'stream' => $this->configGet('storage_path') . '/simple.log',
            'level'  => \Monolog\Logger::DEBUG,
        ]);

        return [
            'class'       => $handlerClass,
            'constructor' => $handlerConstructor,
        ];
    }

    protected function processors(array $config): array
    {
        $result = [];
        if (!isset($config['processors']) && isset($config['processor'])) {
            $config['processors'] = [$config['processor']];
        }

        foreach ($config['processors'] ?? [] as $value) {
            if (is_array($value) && isset($value['class'])) {
                $value = $this->app->make($value['class'], $value['constructor'] ?? []);
            }

            $result[] = $value;
        }

        return $result;
    }

    protected function handlers(array $config): array
    {
        $handlerConfigs = $config['handlers'] ?? [[]];
        $handlers = [];
        $defaultHandlerConfig = $this->getDefaultHandlerConfig($config);
        $defaultFormatterConfig = $this->getDefaultFormatterConfig($config);
        foreach ($handlerConfigs as $value) {
            $class = $value['class'] ?? $defaultHandlerConfig['class'];
            $constructor = $value['constructor'] ?? $defaultHandlerConfig['constructor'];
            if (isset($value['formatter'])) {
                if (!isset($value['formatter']['constructor'])) {
                    $value['formatter']['constructor'] = $defaultFormatterConfig['constructor'];
                }
            }
            $formatterConfig = $value['formatter'] ?? $defaultFormatterConfig;

            $handlers[] = $this->handler($class, $constructor, $formatterConfig);
        }

        return $handlers;
    }

    protected function handler($class, $constructor, $formatterConfig): HandlerInterface
    {
        /** @var HandlerInterface $handler */
        $handler = $this->app->make($class, $constructor);

        if ($handler instanceof FormattableHandlerInterface) {
            $formatterClass = $formatterConfig['class'];
            $formatterConstructor = $formatterConfig['constructor'];

            /** @var FormatterInterface $formatter */
            $formatter = $this->app->make($formatterClass, $formatterConstructor);

            $handler->setFormatter($formatter);
        }

        return $handler;
    }

    /**
     * 获取当前 日志 config 参数
     * @param $key
     * @param $default
     * @return array|\ArrayAccess|mixed
     */
    protected function configGet($key, $default = null)
    {
        return Arr::get($this->cfg, "logging.$key", $default);
    }


    /**
     * @var array
     */
    protected $customerLoggers;

    /**
     * @param $config array 参见
     * @param $name
     * @param $group
     * @return mixed|SimpleLogger
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function channelFile($name = 'simple', array $config = [], $group = 'default')
    {
        if (isset($this->customerLoggers[$group][$name]) && $this->customerLoggers[$group][$name] instanceof Logger) {
            return $this->customerLoggers[$group][$name];
        }
        if (empty($config)) {
            $config = $this->getChannelFileDefaultConfig($name);
        }
        $handlers = $this->handlers($config);
        $processors = $this->processors($config);
        return $this->customerLoggers[$group][$name] = $this->app->make(Logger::class, [
            'name'       => $name,
            'handlers'   => $handlers,
            'processors' => $processors,
        ]);
    }


    /**
     * 自定义配置文件
     * @param $name
     * @return array[]
     */
    protected function getChannelFileDefaultConfig($name)
    {
        return [
            'handlers'   => [
                [
                    'class'       => \Monolog\Handler\StreamHandler::class,
                    'constructor' => [
                        'stream' => $this->configGet('storage_path') . "/$name.log",
                        'level'  => \Monolog\Logger::DEBUG,
                    ],
                    'formatter'   => [
                        'class'       => LineFormatter::class,
                        'constructor' => [
                            'dateFormat' => 'Y-m-d H:i:s',
                        ],
                    ],
                ],
            ],
            'processors' =>
                [
                    [
                        'class'       => TraceIdProcessor::class,
                        'constructor' => [],
                    ],
                ],

        ];
    }

}
