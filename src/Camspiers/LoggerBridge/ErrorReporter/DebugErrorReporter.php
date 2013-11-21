<?php

namespace Camspiers\LoggerBridge\ErrorReporter;

use Camspiers\LoggerBridge\EnvReporter\EnvReporter;
use Debug;

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
     * @param \Exception      $exception
     * @param \SS_HTTPRequest $request
     */
    public function reportError(\Exception $exception, \SS_HTTPRequest $request = null)
    {
        if (!$this->envReporter->isLive()) {
            Debug::showError(
                $exception->getCode(),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                false,
                'Error'
            );
        } else {
            Debug::friendlyError();
        }
    }
}
