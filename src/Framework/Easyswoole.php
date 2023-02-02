<?php

namespace NaturalCloud\Logger\Framework;

use EasySwoole\Component\Context\ContextManager;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use NaturalCloud\Logger\LogManager;
use NaturalCloud\Logger\Support\Str;
use NaturalCloud\Logger\Tap\EasyswooleTapFormatter;
use NaturalCloud\Logger\Tap\TapFormatter;

class Easyswoole
{


    /**
     * 生成 traceId,会默认从 request 中取 ,并设置响应 header 到前端
     * @param Request $request
     * @param Response $response
     * @param $traceKey string
     * @param $traceValueCallable
     * @return void
     */
    public static function trace(
        Request $request,
        Response $response,
        $traceKey = 'x-request-id',
        $traceValueCallable = null
    ) {
        // 日志 traceId
        if ($requestTraceId = $request->getHeader($traceKey)) {
            if (is_array($requestTraceId)) {
                $requestTraceId = $requestTraceId[0];
            }
        } else {
            $requestTraceId = is_callable($traceValueCallable) ? $traceValueCallable() : Str::random(32);
        }
        ContextManager::getInstance()->set($traceKey, $requestTraceId);
        $response->withHeader($traceKey, $requestTraceId);
    }


    /**
     * @param array $logConfig
     * @return LogManager
     */
    public static function makeLogger(array $logConfig)
    {
        return new LogManager($logConfig);
    }


    /**
     * @param $traceKey
     * @return string
     */
    public static function getTraceId($traceKey = '')
    {
        $traceKey = $traceKey ?: 'x-request-id';
        return ContextManager::getInstance()->get($traceKey) ?: '';
    }


    /**
     * 获取logger
     * @return callable|null| \NaturalCloud\Logger\LogManager
     * @throws \Throwable
     */
    public static function getLogger($loggerKey = 'logger-new')
    {
        return \EasySwoole\Component\Di::getInstance()->get($loggerKey);
    }


    /**
     * @param array $config
     * @param $loggerKey
     * @param $logClass
     * @return mixed
     */
    public static function setLogger(array $config, $loggerKey = 'logger-new', $logClass = LogManager::class)
    {
        return \EasySwoole\Component\Di::getInstance()->set($loggerKey, $logClass, $config);
    }

    /**
     * 自定义日志
     * @param $logName
     * @param $loggerKey
     * @param $config
     * @return \Psr\Log\LoggerInterface
     */
    public static function channelFile(
        $logName,
        $loggerKey = 'logger-new',
        $config = ['tap' => [TapFormatter::class, EasyswooleTapFormatter::class]]
    ) {
        return self::getLogger($loggerKey)->channelFile($logName, $config);
    }

    /**
     * 终端打印
     * @param $loggerKey
     * @return \Psr\Log\LoggerInterface
     */
    public static function logConsole($loggerKey = 'logger-new')
    {
        return self::getLogger($loggerKey)->channel('console');
    }
}