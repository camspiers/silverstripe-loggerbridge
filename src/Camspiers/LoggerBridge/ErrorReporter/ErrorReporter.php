<?php

namespace Camspiers\LoggerBridge\ErrorReporter;

/**
 * Class ErrorReporter
 */
interface ErrorReporter
{
    public function reportError($exception, \SS_HTTPRequest $request = null);
}
