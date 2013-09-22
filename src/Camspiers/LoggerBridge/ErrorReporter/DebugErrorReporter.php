<?php

namespace Camspiers\LoggerBridge\ErrorReporter;

use Camspiers\LoggerBridge\EnvReporter\EnvReporter;
use Debug;
use Director;

/**
 * Class DebugErrorReporter
 */
class DebugErrorReporter implements ErrorReporter
{
    /**
     * @var \Camspiers\LoggerBridge\EnvReporter\EnvReporter
     */
    protected $envReporter;
    /**
     * @param \Camspiers\LoggerBridge\EnvReporter\EnvReporter $envReporter
     */
    public function __construct(EnvReporter $envReporter)
    {
        $this->envReporter = $envReporter;
    }
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
        if (!$this->envReporter->isLive()) {
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
