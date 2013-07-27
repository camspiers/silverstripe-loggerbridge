<?php

namespace Camspiers\LoggerBridge\ErrorReporter;

use Debug;
use Director;

/**
 * Class DebugErrorReporter
 */
class DebugErrorReporter implements ErrorReporter
{
    /**
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     * @param $errtype
     */
    public function reportError(
        $errno,
        $errstr,
        $errfile,
        $errline,
        $errtype
    ) {
        if (!Director::isLive()) {
            Debug::showError(
                $errno,
                $errstr,
                $errfile,
                $errline,
                false,
                $errtype
            );
        } else {
            Debug::friendlyError();
        }
    }
}
