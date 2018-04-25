<?php

namespace Camspiers\LoggerBridge\BacktraceReporter;

/**
 * Class BacktraceReporter
 * @package Camspiers\LoggerBridge\BacktraceReporter
 */
interface BacktraceReporter
{
    /**
     * Returns a backtrace for logging
     * @param  mixed      $exception
     * @return array
     */
    public function getBacktrace($exception = null);
}
