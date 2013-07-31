<?php

namespace Camspiers\LoggerBridge;

use Camspiers\LoggerBridge\EnvReporter\DirectorEnvReporter;
use Camspiers\LoggerBridge\EnvReporter\EnvReporter;
use Camspiers\LoggerBridge\ErrorReporter\DebugErrorReporter;
use Camspiers\LoggerBridge\ErrorReporter\ErrorReporter;
use Psr\Log\LoggerInterface;

/**
 * Enables global SilverStripe logging with a PSR-3 logger like Monolog.
 *
 * The logger is attached in by using a RequestProcessor filter. This behaviour is required
 * so the logger is attached after the environment only and except rules in yml are applied.
 *
 * @author Cam Spiers <camspiers@gmail.com>
 */
class LoggerBridge implements \RequestFilter
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Camspiers\LoggerBridge\ErrorReporter\ErrorReporter
     */
    protected $errorReporter;

    /**
     * @var \Camspiers\LoggerBridge\EnvReporter\EnvReporter
     */
    protected $envReporter;

    /**
     * @var bool|null
     */
    protected $registered;

    /**
     * @var bool
     */
    protected $reportErrorsWhenNotLive = true;

    /**
     * @var null|int
     */
    protected $reserveMemory;

    /**
     * @var null|callable
     */
    protected $errorHandler;

    /**
     * @var null|callable
     */
    protected $exceptionHandler;

    /**
     * @var \SS_HTTPRequest
     */
    protected $request;

    /**
     * @var \DataModel
     */
    protected $model;

    /**
     * @var int
     */
    protected $backtraceLimit = 0;

    /**
     * Defines the way error types are logged
     * @var
     */
    protected $errorLogGroups = array(
        'error'   => array(
            E_ERROR,
            E_CORE_ERROR,
            E_USER_ERROR
        ),
        'warning' => array(
            E_WARNING,
            E_CORE_WARNING,
            E_USER_WARNING
        ),
        'notice'  => array(
            E_NOTICE,
            E_USER_NOTICE,
            E_DEPRECATED,
            E_USER_DEPRECATED,
            E_STRICT
        )
    );

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param bool                     $reportErrorsWhenNotLive If false stops the display of SilverStripe errors
     * @param null                     $reserveMemory           The amount of memory to reserve for out of memory errors
     */
    public function __construct(
        LoggerInterface $logger,
        $reportErrorsWhenNotLive = true,
        $reserveMemory = null
    ) {
        $this->logger = $logger;
        $this->reportErrorsWhenNotLive = (bool) $reportErrorsWhenNotLive;
        if ($reserveMemory !== null) {
            $this->setReserveMemory($reserveMemory);
        }
    }

    /**
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param \Camspiers\LoggerBridge\ErrorReporter\ErrorReporter $errorReporter
     */
    public function setErrorReporter(ErrorReporter $errorReporter)
    {
        $this->errorReporter = $errorReporter;
    }

    /**
     * @return \Camspiers\LoggerBridge\ErrorReporter\ErrorReporter
     */
    public function getErrorReporter()
    {
        $this->errorReporter = $this->errorReporter ? : new DebugErrorReporter();

        return $this->errorReporter;
    }

    /**
     * @param \Camspiers\LoggerBridge\EnvReporter\EnvReporter $envReporter
     */
    public function setEnvReporter(EnvReporter $envReporter)
    {
        $this->envReporter = $envReporter;
    }

    /**
     * @return \Camspiers\LoggerBridge\EnvReporter\EnvReporter
     */
    public function getEnvReporter()
    {
        $this->envReporter = $this->envReporter ? : new DirectorEnvReporter();

        return $this->envReporter;
    }

    /**
     * @return boolean
     */
    public function getRegistered()
    {
        return $this->registered;
    }

    /**
     * @param array $errorLogGroups
     */
    public function setErrorLogGroups($errorLogGroups)
    {
        if (is_array($errorLogGroups)) {
            $this->errorLogGroups = $errorLogGroups;
        }
    }

    /**
     * @return mixed
     */
    public function getErrorLogGroups()
    {
        return $this->errorLogGroups;
    }

    /**
     * @param boolean $reportErrorsWhenNotLive
     */
    public function setReportErrorsWhenNotLive($reportErrorsWhenNotLive)
    {
        $this->reportErrorsWhenNotLive = (bool) $reportErrorsWhenNotLive;
    }

    /**
     * @return boolean
     */
    public function getReportErrorsWhenNotLive()
    {
        return $this->reportErrorsWhenNotLive;
    }

    /**
     * @param int $backtraceLimit
     */
    public function setBacktraceLimit($backtraceLimit)
    {
        $this->backtraceLimit = $backtraceLimit;
    }

    /**
     * @return int
     */
    public function getBacktraceLimit()
    {
        return $this->backtraceLimit;
    }

    /**
     * @param string|int $reserveMemory
     */
    public function setReserveMemory($reserveMemory)
    {
        if (is_string($reserveMemory)) {
            $this->reserveMemory = self::translateMemoryLimit($reserveMemory);
        } elseif (is_int($reserveMemory)) {
            $this->reserveMemory = $reserveMemory;
        }
    }

    /**
     * @return int|null
     */
    public function getReserveMemory()
    {
        return $this->reserveMemory;
    }

    /**
     * This hook function is executed from RequestProcessor before the request starts
     * @param  \SS_HTTPRequest $request
     * @param  \Session        $session
     * @param  \DataModel      $model
     * @return bool
     */
    public function preRequest(
        \SS_HTTPRequest $request,
        \Session $session,
        \DataModel $model
    ) {
        $this->registerGlobalHandlers($request, $model);

        return true;
    }

    /**
     * This hook function is executed from RequestProcessor after the request ends
     * @param  \SS_HTTPRequest  $request
     * @param  \SS_HTTPResponse $response
     * @param  \DataModel       $model
     * @return bool
     */
    public function postRequest(
        \SS_HTTPRequest $request,
        \SS_HTTPResponse $response,
        \DataModel $model
    ) {
        $this->deregisterGlobalHandlers();

        return true;
    }

    /**
     * Registers global error handlers
     * @param \SS_HTTPRequest $request
     * @param \DataModel      $model
     */
    public function registerGlobalHandlers(
        \SS_HTTPRequest $request = null,
        \DataModel $model = null
    ) {
        if (!$this->registered) {
            // If the developer wants to see errors in dev mode then don't let php display them
            if (!$this->getEnvReporter()->isLive()) {
                ini_set('display_errors', !$this->reportErrorsWhenNotLive);
            }
            // Store the request and model for use in reporting
            $this->request = $request;
            $this->model = $model;
            // Store the previous error handler if there was any
            $this->registerErrorHandler();
            // Store the previous exception handler if there was any
            $this->registerExceptionHandler();
            // If the shutdown function hasn't been registered register it
            if ($this->registered === null) {
                $this->registerFatalErrorHandler();
                if ($this->reserveMemory !== null) {
                    $this->reserveMemory();
                }
            }
            $this->registered = true;
        }
    }

    /**
     * Removes handlers we have added, and restores others if possible
     */
    public function deregisterGlobalHandlers()
    {
        if ($this->registered) {
            $this->request = null;
            $this->model = null;
            // Restore the previous error handler if available
            set_error_handler(
                is_callable($this->errorHandler)
                    ? $this->errorHandler
                    : function () {}
            );
            // Restore the previous exception handler if available
            set_exception_handler(
                is_callable($this->exceptionHandler)
                    ? $this->exceptionHandler
                    : function () {}
            );
            $this->registered = false;
        }
    }

    /**
     * Registers the error handler
     */
    protected function registerErrorHandler()
    {
        $this->errorHandler = set_error_handler(array($this, 'errorHandler'));
    }

    /**
     * Registers the exception handler
     */
    protected function registerExceptionHandler()
    {
        $this->exceptionHandler = set_exception_handler(array($this, 'exceptionHandler'));
    }

    /**
     * Registers the fatal error handler
     */
    protected function registerFatalErrorHandler()
    {
        register_shutdown_function(array($this, 'fatalHandler'));
    }

    /**
     * Handlers general errors, user, warn and notice
     * But the handler honours the error_reporting set in the environment
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     * @return bool|string|void
     */
    public function errorHandler($errno, $errstr, $errfile, $errline)
    {
        // Honour error suppression through @
        if (($errorReporting = error_reporting()) === 0) {
            return;
        }

        foreach ($this->errorLogGroups as $logType => $errorTypes) {
            if (in_array($errno, $errorTypes)) {
                // Log all errors regardless of type
                $this->logger->$logType(
                    $errstr,
                    array(
                        'errfile'   => $errfile,
                        'errline'   => $errline,
                        'request'   => $this->formatArray($this->request),
                        'model'     => $this->formatArray($this->model),
                        'backtrace' => $this->formatArray($this->getBacktrace())
                    )
                );

                // Check the error_reporting level in comparison with the $errno (honouring the environment)
                // and (secondly)
                // As long as $reportErrorsWhenNotLive is on or the site is live
                // then do the error reporting
                if (
                    ($errno & $errorReporting) === $errno
                    &&
                    ($this->reportErrorsWhenNotLive || $this->getEnvReporter()->isLive())
                ) {
                    $this->getErrorReporter()->reportError(
                        $errno,
                        $errstr,
                        $errfile,
                        $errline,
                        ucfirst($logType)
                    );
                }

                break;
            }
        }
    }

    /**
     * Handles uncaught exceptions
     * @param  \Exception  $exception
     * @return string|void
     */
    public function exceptionHandler(\Exception $exception)
    {
        $this->logger->error(
            $message = 'Uncaught ' . get_class($exception) . ': ' . $exception->getMessage(),
            array(
                'errfile'   => $exception->getFile(),
                'errline'   => $exception->getLine(),
                'request'   => $this->formatArray($this->request),
                'model'     => $this->formatArray($this->model),
                'backtrace' => $this->formatArray($this->getBacktrace())
            )
        );

        // Exceptions must be reported in general because they stop the regular display of the page
        if ($this->reportErrorsWhenNotLive || $this->getEnvReporter()->isLive()) {
            $this->getErrorReporter()->reportError(
                E_USER_ERROR,
                $message,
                $exception->getFile(),
                $exception->getLine(),
                'Error'
            );
        }
    }

    /**
     * Handles fatal errors
     * If we are registered, and there is a fatal error then log and try to gracefully handle error output
     */
    public function fatalHandler()
    {
        if (
            $this->getRegistered()
            &&
            $error = $this->getLastErrorFatal()
        ) {
            if ($this->reserveMemory !== null) {
                $this->restoreMemory();
            }

            $this->logger->critical(
                $error['message'],
                array(
                    'errfile'   => $error['file'],
                    'errline'   => $error['line'],
                    'backtrace' => $this->formatArray($this->getBacktrace())
                )
            );

            // Fatal errors should be reported when live as they stop the display of regular output
            if ($this->reportErrorsWhenNotLive || $this->getEnvReporter()->isLive()) {
                $this->getErrorReporter()->reportError(
                    E_CORE_ERROR,
                    $error['message'],
                    $error['file'],
                    $error['line'],
                    'Fatal Error'
                );
            }
        }
    }
    /**
     * @return array|bool
     */
    protected function getLastErrorFatal()
    {
        $error = error_get_last();
        if (
            $error
            &&
            in_array(
                $error['type'],
                array(
                    E_ERROR,
                    E_CORE_ERROR
                )
            )
        ) {
            return $error;
        } else {
            return false;
        }
    }

    /**
     * Sets the memory limit less by the reserveMemory amount
     */
    protected function reserveMemory()
    {
        $this->changeMemoryLimit(-$this->reserveMemory);
    }

    /**
     * Restores the original memory limit so fatal out of memory errors can be properly processed
     */
    protected function restoreMemory()
    {
        $this->changeMemoryLimit($this->reserveMemory);
    }

    /**
     * Change memory_limit by specified amount
     * @param int $amount
     */
    protected function changeMemoryLimit($amount)
    {
        ini_set(
            'memory_limit',
            self::translateMemoryLimit(ini_get('memory_limit')) + $amount
        );
    }

    /**
     * Translate the memory limit string to a int in bytes.
     * Credit SilverStripe core/Core.php
     * @param $memoryLimit
     * @return float
     */
    protected static function translateMemoryLimit($memoryLimit)
    {
        switch (strtolower(substr($memoryLimit, -1))) {
            case "k":
                return round(substr($memoryLimit, 0, -1) * 1024);
            case "m":
                return round(substr($memoryLimit, 0, -1) * 1024 * 1024);
            case "g":
                return round(substr($memoryLimit, 0, -1) * 1024 * 1024 * 1024);
            default:
                return round($memoryLimit);
        }
    }

    /**
     * @return array
     */
    protected function getBacktrace()
    {
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            return debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->backtraceLimit);
        } else {
            return debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }
    }

    /**
     * @param $arr
     * @return mixed
     */
    protected function formatArray($arr)
    {
        return print_r($arr, true);
    }
}
