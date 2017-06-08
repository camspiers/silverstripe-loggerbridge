<?php

namespace Camspiers\LoggerBridge\BacktraceReporter;

/**
 * Class FilteredBacktraceReporter
 */
class FilteredBacktraceReporter extends BasicBacktraceReporter
{
    /**
     * An array of function names to be excluded
     * @var array
     */
    protected $filteredFunctions = array();

    /**
     * Takes an array of functions names that should be filtered
     * @param  array             $filteredFunctions
     * @throws \RuntimeException
     */
    public function __construct($filteredFunctions)
    {
        if (is_array($filteredFunctions)) {
            $this->filteredFunctions = $filteredFunctions;
        } else {
            throw new \RuntimeException("Filtered functions argument to FilteredBacktraceReporter must be an array");
        }
    }

    /**
     * Returns a filtered backtrace using regular expressions
     * @param  \Throwable $exception
     * @return array|void
     */
    public function getBacktrace(\Throwable $exception = null)
    {
        $backtrace = parent::getBacktrace($exception);

        foreach ($backtrace as $index => $backtraceCall) {
            $functionName = $this->buildFunctionName($backtraceCall);
            foreach ($this->filteredFunctions as $pattern) {
                if (preg_match('/'.$pattern.'/', $functionName)) {
                    unset($backtrace[$index]);
                    break;
                }
            }
        }

        return array_values($backtrace);
    }

    /**
     * Builds a string representation of the backtrace call
     * @param $backtraceCall
     * @return string
     */
    protected function buildFunctionName($backtraceCall)
    {
        if (isset($backtraceCall['class'])) {
            return $backtraceCall['class'] . $backtraceCall['type'] . $backtraceCall['function'];
        } else {
            return $backtraceCall['function'];
        }
    }
}
