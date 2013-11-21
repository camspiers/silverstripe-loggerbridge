<?php

namespace Camspiers\LoggerBridge\ErrorReporter;

use Camspiers\LoggerBridge\EnvReporter\EnvReporter;
use Debug;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 * Class DebugErrorReporter
 */
class WhoopsPrettyErrorReporter implements ErrorReporter
{
    /**
     * @var \Whoops\Handler\PrettyPageHandler
     */
    protected $prettyHandler;

    /**
     * @var \Camspiers\LoggerBridge\EnvReporter\EnvReporter
     */
    protected $envReporter;

    /**
     * @param \Whoops\Handler\PrettyPageHandler $prettyHandler
     * @param \Camspiers\LoggerBridge\EnvReporter\EnvReporter $envReporter
     */
    public function __construct(PrettyPageHandler $prettyHandler, EnvReporter $envReporter)
    {
        $this->prettyHandler = $prettyHandler;
        $this->envReporter = $envReporter;
    }

    /**
     * @param \Exception $exception
     * @param \SS_HTTPRequest $request
     */
    public function reportError(\Exception $exception, \SS_HTTPRequest $request = null) {
        if (!$this->envReporter->isLive()) {
            $whoops = new Run();
            $whoops->pushHandler($this->prettyHandler)->handleException($exception);
        } else {
            Debug::friendlyError();
        }
    }
}
