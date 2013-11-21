<?php

namespace Camspiers\LoggerBridge\BacktraceReporter;

/**
 * Class BasicBacktraceReporter
 */
class BasicBacktraceReporter implements BacktraceReporter
{
    /**
     * Backtrace limit. Defaults to 0
     * @var int
     */
    protected $backtraceLimit = 0;

    /**
     * Sets the limit on the number of backtrace calls shown
     * @param int $backtraceLimit
     */
    public function setBacktraceLimit($backtraceLimit)
    {
        $this->backtraceLimit = (int) $backtraceLimit;
    }

    /**
     * Returns the backtrace limit
     * @return int
     */
    public function getBacktraceLimit()
    {
        return $this->backtraceLimit;
    }

    /**
     * Returns a basic backtrace
     * @param  \Exception $exception
     * @return array
     */
    public function getBacktrace(\Exception $exception = null)
    {
        $skipLimit = false;

        if ($exception instanceof \Exception) {
            $backtrace = $exception->getTrace();
        } elseif (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->backtraceLimit);
            $skipLimit = true;
        } else {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }

        if ($this->backtraceLimit > 0 && !$skipLimit) {
            $backtrace = array_slice($backtrace, 0, $this->backtraceLimit);
        }

        return $backtrace;
    }
}
