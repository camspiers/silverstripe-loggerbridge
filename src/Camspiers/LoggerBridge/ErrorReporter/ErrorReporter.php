<?php

namespace Camspiers\LoggerBridge\ErrorReporter;

/**
 * Class ErrorReporter
 */
interface ErrorReporter
{
    /**
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     * @param $errtype
     */
    public function reportError($errno, $errstr, $errfile, $errline, $errtype);
}
