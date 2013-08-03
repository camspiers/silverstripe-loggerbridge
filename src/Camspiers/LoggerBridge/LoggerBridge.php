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
    protected $displayErrorsWhenNotLive = true;

    /**
     * @var int
     */
    protected $reserveMemory = 5242880; // 5M

    /**
     * @var int|null
     */
    protected $reportLevel;

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
     * @param bool                     $displayErrorsWhenNotLive If false stops the display of SilverStripe errors
     * @param null                     $reserveMemory           The amount of memory to reserve for out of memory errors
     * @param null|int                 $reportLevel             Allow the specification of a reporting level
     */
    public function __construct(
        LoggerInterface $logger,
        $displayErrorsWhenNotLive = true,
        $reserveMemory = null,
        $reportLevel = null
    ) {
        $this->logger = $logger;
        $this->displayErrorsWhenNotLive = (bool) $displayErrorsWhenNotLive;
        if ($reserveMemory !== null) {
            $this->setReserveMemory($reserveMemory);
        }
        // If a specific reportLevel isn't set use error_reporting
        // It can be useful to set a reportLevel when you want to override SilverStripe live settings
        $this->reportLevel = $reportLevel !== null ? $reportLevel : error_reporting();
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
     * @param boolean $displayErrorsWhenNotLive
     */
    public function setDisplayErrorsWhenNotLive($displayErrorsWhenNotLive)
    {
        $this->displayErrorsWhenNotLive = (bool) $displayErrorsWhenNotLive;
    }

    /**
     * @return boolean
     */
    public function getDisplayErrorsWhenNotLive()
    {
        return $this->displayErrorsWhenNotLive;
    }

    /**
     * @param int|null $reportLevel
     */
    public function setReportLevel($reportLevel)
    {
        $this->reportLevel = $reportLevel;
    }

    /**
     * @return int|null
     */
    public function getReportLevel()
    {
        return $this->reportLevel;
    }

    /**
     * Only applies in 5.4
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
                ini_set('display_errors', !$this->displayErrorsWhenNotLive);
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
                
                // If suhosin is relevant then decrease the memory_limit by the reserveMemory amount
                // otherwise we should be able to increase the memory by our reserveMemory amount without worry
                if ($this->isSuhosinRelevant()) {
                    $this->ensureSuhosinMemory();
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
        $this->errorHandler = set_error_handler(array($this, 'errorHandler'), $this->reportLevel);
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
     * Handles general errors, user, warn and notice
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
                        'request'   => $this->format($this->request),
                        'model'     => $this->format($this->model),
                        'backtrace' => $this->format($this->getBacktrace())
                    )
                );

                // Check that it is the type of error to report
                // Check the error_reporting level in comparison with the $errno (honouring the environment)
                // Check that $displayErrorsWhenNotLive is on or the site is live
                if (
                    $logType === 'error'
                    &&
                    ($errno & $errorReporting) === $errno
                    &&
                    ($this->displayErrorsWhenNotLive || $this->getEnvReporter()->isLive())
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
                'request'   => $this->format($this->request),
                'model'     => $this->format($this->model),
                'backtrace' => $this->format($this->getBacktrace())
            )
        );

        // Exceptions must be reported in general because they stop the regular display of the page
        if ($this->displayErrorsWhenNotLive || $this->getEnvReporter()->isLive()) {
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
     * In cases where memory is exhausted increase the memory_limit to allow for logging
     */
    public function fatalHandler()
    {
        if (
            $this->getRegistered()
            &&
            $error = $this->getLastErrorFatal()
        ) {
            if ($this->isMemoryExhaustedError($error)) {
                // We can safely change the memory limit be the reserve amount because if suhosin is relevant
                // the memory will have been decreased prior to exhaustion
                $this->changeMemoryLimit($this->reserveMemory);
            }

            $this->logger->critical(
                $error['message'],
                array(
                    'errfile'   => $error['file'],
                    'errline'   => $error['line'],
                    'backtrace' => $this->format($this->getBacktrace())
                )
            );

            // Fatal errors should be reported when live as they stop the display of regular output
            if ($this->displayErrorsWhenNotLive || $this->getEnvReporter()->isLive()) {
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
     * Returns whether or not the last error was fatal, if it was then return the error
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
     * Get a backtrace. Allowing for limiting when in 5.4
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
     * Formats objects and array for logging
     * @param $arr
     * @return mixed
     */
    protected function format($arr)
    {
        return print_r($arr, true);
    }

    /**
     * Returns whether or not the passed in error is a memory exhausted error
     * @param $error array
     * @return bool
     */
    protected function isMemoryExhaustedError($error)
    {
        return
            isset($error['message'])
            && stripos($error['message'], 'memory') !== false
            && stripos($error['message'], 'exhausted') !== false;
    }

    /**
     * Change memory_limit by specified amount
     * @param $amount
     */
    protected function changeMemoryLimit($amount)
    {
        ini_set(
            'memory_limit',
            $this->getMemoryLimit() + $amount
        );
    }

    /**
     * Translate the memory limit string to a int in bytes.
     * @param $memoryLimit
     * @return int
     */
    protected static function translateMemoryLimit($memoryLimit)
    {
        $unit = strtolower(substr($memoryLimit, -1, 1));
        $memoryLimit = (int) $memoryLimit;
        switch($unit) {
            case 'g':
                $memoryLimit *= 1024;
            case 'm':
                $memoryLimit *= 1024;
            case 'k':
                $memoryLimit *= 1024;
        }
        return $memoryLimit;
    }

    /**
     * @return int
     */
    protected function getMemoryLimit()
    {
        return $this->translateMemoryLimit(ini_get('memory_limit'));
    }

    /**
     * @return int
     */
    protected function getSuhosinMemoryLimit()
    {
        return $this->translateMemoryLimit(ini_get('suhosin.memory_limit'));
    }
    
    /**
     * Checks if suhosin is enabled and the memory_limit is closer to suhosin.memory_limit than reserveMemory
     * It is in this case where it is relevant to decrease the memory available to the script before it uses all
     * available memory so when we need to increase the memory limit we can do so
     * @return bool
     */
    protected function isSuhosinRelevant()
    {
        return extension_loaded('suhosin') && $this->getSuhosinMemoryDifference() < $this->reserveMemory;
    }

    /**
     * Returns how close the max memory limit is to the current memory limit
     * @return int
     */
    protected function getSuhosinMemoryDifference()
    {
        return $this->getSuhosinMemoryLimit() - $this->getMemoryLimit();
    }
    
    /**
     * Set the memory_limit so we have enough to handle errors when suhosin is relevant
     */
    protected function ensureSuhosinMemory()
    {
        ini_set(
            'memory_limit',
            $this->getSuhosinMemoryLimit() - $this->reserveMemory
        );
    }
}
