<?php

namespace NaturalCloud\Logger\Processor;

use Monolog\Processor\ProcessorInterface;
use NaturalCloud\Logger\Framework\Easyswoole;
use NaturalCloud\Logger\Logger;

class TraceIdProcessor implements ProcessorInterface
{

    protected $traceKey;
    /**
     * @var mixed|string
     */
    protected $logTraceKey;
    /**
     * @var mixed|null
     */
    protected $traceCallable;

    /**
     * 自定义给定的日志实例
     *
     * @param array $record
     * @return void
     */
    public function __invoke(array $record): array
    {
        $record['extra'][$this->logTraceKey] = is_callable($this->traceCallable) ? ($this->traceCallable)() : ''; // todo GET traceId
        return $record;
    }


    public function __construct($traceKey = 'x-request-id', $logTraceKey = 'trace_id', $traceCallable = null)
    {
        $this->traceKey = $traceKey;
        $this->logTraceKey = $logTraceKey;
        $this->traceCallable = $traceCallable;
    }
}