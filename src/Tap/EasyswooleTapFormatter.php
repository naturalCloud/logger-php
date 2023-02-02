<?php

namespace NaturalCloud\Logger\Tap;

use NaturalCloud\Logger\Logger;
use NaturalCloud\Logger\Processor\EasyswooleTraceIdProcessor;

class EasyswooleTapFormatter
{

    /**
     * 自定义给定的日志实例
     *
     * @param Logger $logger
     * @return void
     */
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(new EasyswooleTraceIdProcessor());
        }
    }
}