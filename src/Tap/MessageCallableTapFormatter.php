<?php

namespace NaturalCloud\Logger\Tap;

use Monolog\Handler\HandlerInterface;
use NaturalCloud\Logger\Logger;
use NaturalCloud\Logger\Processor\TraceIdProcessor;

class MessageCallableTapFormatter
{

    /**
     *  更改日志实例,
     *  重设 HandlerInterface Formatter , Processor....
     * @param Logger $logger
     * @return void
     */
    public function __invoke($logger)
    {

        /** 设置 Logger message 格式化 **/
        $logger->setFormatMessageCallable(function ($message) {
            // 自定义 $message 格式化,当 message 是对象,数组等非可自动转化成字符串的
            if (is_array($message) || (is_object($message) && !$message instanceof \Throwable)) {
                $message = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            return $message;
        });

        foreach ($logger->getHandlers() as $handler) {
            /** @var $handler HandlerInterface * */
        }
    }
}