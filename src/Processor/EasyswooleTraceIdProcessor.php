<?php

namespace NaturalCloud\Logger\Processor;

use Monolog\Processor\ProcessorInterface;
use NaturalCloud\Logger\Framework\Easyswoole;
use NaturalCloud\Logger\Logger;

class EasyswooleTraceIdProcessor implements ProcessorInterface
{

    protected $traceKey;
    /**
     * @var mixed|string
     */
    protected $logTraceKey;

    /**
     * 自定义给定的日志实例
     *
     * @param array $record
     * @return void
     */
    public function __invoke(array $record): array
    {
        $record['extra'][$this->logTraceKey] = Easyswoole::getTraceId($this->traceKey);
        return $record;
    }


    public function __construct($traceKey = 'x-request-id', $logTraceKey = 'trace_id')
    {
        $this->traceKey = $traceKey;
        $this->logTraceKey = $logTraceKey;
    }
}