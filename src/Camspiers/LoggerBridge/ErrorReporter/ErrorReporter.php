<?php

namespace Camspiers\LoggerBridge\ErrorReporter;

/**
 * Class ErrorReporter
 */
interface ErrorReporter
{
    public function reportError(\Exception $exception, \SS_HTTPRequest $request = null);
}
