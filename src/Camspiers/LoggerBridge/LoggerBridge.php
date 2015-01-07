<?php

namespace Camspiers\LoggerBridge;

use Camspiers\LoggerBridge\BacktraceReporter\BacktraceReporter;
use Camspiers\LoggerBridge\BacktraceReporter\BasicBacktraceReporter;
use Camspiers\LoggerBridge\EnvReporter\DirectorEnvReporter;
use Camspiers\LoggerBridge\EnvReporter\EnvReporter;
use Camspiers\LoggerBridge\ErrorReporter\DebugErrorReporter;
use Camspiers\LoggerBridge\ErrorReporter\ErrorReporter;
use Psr\Log\LoggerInterface;

/**
 * Enables global SilverStripe logging with a PSR-3 logger like Monolog.
 *
 * The logger is attached by using a Request Processor filter. This behaviour is required
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
     * @var \Camspiers\LoggerBridge\BacktraceReporter\BacktraceReporter
     */
    protected $backtraceReporter;

    /**
     * @var bool|null
     */
    protected $registered;

    /**
     * @var bool
     */
    protected $showErrors = true;

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
     * @var bool
     */
    protected $reportBacktrace = false;

    /**
     * Defines the way error types are logged
     * @var
     */
    protected $errorLogGroups = array(
        'error'   => array(
            E_ERROR,
            E_CORE_ERROR,
            E_USER_ERROR,
            E_PARSE,
            E_COMPILE_ERROR,
            E_RECOVERABLE_ERROR
        ),
        'warning' => array(
            E_WARNING,
            E_CORE_WARNING,
            E_USER_WARNING,
            E_NOTICE,
            E_USER_NOTICE,
            E_DEPRECATED,
            E_USER_DEPRECATED,
            E_STRICT
        )
    );
    
    /**
     * Defines what errors should terminate
     */
    protected $terminatingErrors = array(
        E_ERROR,
        E_CORE_ERROR,
        E_USER_ERROR,
        E_PARSE,
        E_COMPILE_ERROR,
        E_RECOVERABLE_ERROR
    );

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param bool                     $showErrors    If false stops the display of SilverStripe errors
     * @param null                     $reserveMemory The amount of memory to reserve for out of memory errors
     * @param null|int                 $reportLevel   Allow the specification of a reporting level
     */
    public function __construct(
        LoggerInterface $logger,
        $showErrors = true,
        $reserveMemory = null,
        $reportLevel = null
    ) {
        $this->logger = $logger;
        $this->showErrors = (bool) $showErrors;
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
        $this->errorReporter = $this->errorReporter ? : new DebugErrorReporter($this->getEnvReporter());

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
     * @param \Camspiers\LoggerBridge\BacktraceReporter\BacktraceReporter $backtraceReporter
     */
    public function setBacktraceReporter(BacktraceReporter $backtraceReporter)
    {
        $this->backtraceReporter = $backtraceReporter;
    }

    /**
     * @return \Camspiers\LoggerBridge\BacktraceReporter\BacktraceReporter
     */
    public function getBacktraceReporter()
    {
        $this->backtraceReporter = $this->backtraceReporter ? : new BasicBacktraceReporter();

        return $this->backtraceReporter;
    }

    /**
     * @return boolean
     */
    public function isRegistered()
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
     * @param boolean $showErrors
     */
    public function setShowErrors($showErrors)
    {
        $this->showErrors = (bool) $showErrors;
    }

    /**
     * @return boolean
     */
    public function isShowErrors()
    {
        return $this->showErrors;
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
     * @param bool $reportBacktrace
     */
    public function setReportBacktrace($reportBacktrace)
    {
        $this->reportBacktrace = $reportBacktrace;
    }

    /**
     * @param string|int $reserveMemory
     */
    public function setReserveMemory($reserveMemory)
    {
        if (is_string($reserveMemory)) {
            $this->reserveMemory = $this->translateMemoryLimit($reserveMemory);
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
     * @SuppressWarnings("unused")
     */
    public function preRequest(
        \SS_HTTPRequest $request,
        \Session $session,
        \DataModel $model
    ) {
        $this->registerGlobalHandlers();

        return true;
    }

    /**
     * This hook function is executed from RequestProcessor after the request ends
     * @param  \SS_HTTPRequest  $request
     * @param  \SS_HTTPResponse $response
     * @param  \DataModel       $model
     * @return bool
     * @SuppressWarnings("unused")
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
     */
    public function registerGlobalHandlers() {
        if (!$this->registered) {
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
            // Restore the previous error handler if available
            set_error_handler(
                is_callable($this->errorHandler) ? $this->errorHandler : function () { }
            );
            // Restore the previous exception handler if available
            set_exception_handler(
                is_callable($this->exceptionHandler) ? $this->exceptionHandler : function () { }
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
            return true;
        }
        
        $logType = null;

        foreach ($this->errorLogGroups as $candidateLogType => $errorTypes) {
            if (in_array($errno, $errorTypes)) {
                $logType = $candidateLogType;
                break;
            }
        }
        
        if (is_null($logType)) {
            throw new \Exception(sprintf(
                "No log type found for errno '%s'",
                $errno
            ));
        }
        
        // Log all errors regardless of type
        $context = array(
            'file' => $errfile,
            'line' => $errline
        );
    
        if ($this->reportBacktrace) {
            $context['backtrace'] = $this->getBacktraceReporter()->getBacktrace();
        }
    
        $this->logger->$logType($errstr, $context);
    
        // Check the error_reporting level in comparison with the $errno (honouring the environment)
        // And check that $showErrors is on or the site is live
        if (($errno & $errorReporting) === $errno &&
            ($this->showErrors || $this->getEnvReporter()->isLive())) {
            $this->getErrorReporter()->reportError(
                $this->createException(
                    $errstr,
                    $errno,
                    $errfile,
                    $errline
                )
            );
        }
            
        if (in_array($errno, $this->terminatingErrors)) {
            $this->terminate();
        }
        
        // ignore the usually handling of this type of error
        return true;
    }

    /**
     * Handles uncaught exceptions
     * @param  \Exception  $exception
     * @return string|void
     */
    public function exceptionHandler(\Exception $exception)
    {
        $context = array(
            'file'    => $exception->getFile(),
            'line'    => $exception->getLine()
        );

        if ($this->reportBacktrace) {
            $context['backtrace'] = $this->getBacktraceReporter()->getBacktrace($exception);
        }

        $this->logger->error(
            $message = 'Uncaught ' . get_class($exception) . ': ' . $exception->getMessage(),
            $context
        );

        // Exceptions must be reported in general because they stop the regular display of the page
        if ($this->showErrors || $this->getEnvReporter()->isLive()) {
            $this->getErrorReporter()->reportError($exception);
        }
    }

    /**
     * Handles fatal errors
     * If we are registered, and there is a fatal error then log and try to gracefully handle error output
     * In cases where memory is exhausted increase the memory_limit to allow for logging
     */
    public function fatalHandler()
    {
        $error = $this->getLastError();
        
        if ($this->isRegistered() && $this->isFatalError($error)) {
            if (defined('FRAMEWORK_PATH')) {
                chdir(FRAMEWORK_PATH);
            }

            if ($this->isMemoryExhaustedError($error)) {
                // We can safely change the memory limit be the reserve amount because if suhosin is relevant
                // the memory will have been decreased prior to exhaustion
                $this->changeMemoryLimit($this->reserveMemory);
            }

            $context = array(
                'file' => $error['file'],
                'line' => $error['line']
            );

            if ($this->reportBacktrace) {
                $context['backtrace'] = $this->getBacktraceReporter()->getBacktrace();
            }

            $this->logger->critical($error['message'], $context);

            // Fatal errors should be reported when live as they stop the display of regular output
            if ($this->showErrors || $this->getEnvReporter()->isLive()) {
                $this->getErrorReporter()->reportError(
                    $this->createException(
                        $error['message'],
                        $error['type'],
                        $error['file'],
                        $error['line']
                    )
                );
            }
        }
    }

    /**
     * @param $message
     * @param $code
     * @param $filename
     * @param $lineno
     * @return \ErrorException
     */
    protected function createException($message, $code, $filename, $lineno)
    {
        return new \ErrorException(
            $message,
            $code,
            0,
            $filename,
            $lineno
        );
    }

    /**
     * Returns whether or not the last error was fatal
     * @param $error
     * @return bool
     */
    protected function isFatalError($error)
    {
        return $error && in_array(
            $error['type'],
            array(
                E_ERROR,
                E_PARSE,
                E_CORE_ERROR,
                E_COMPILE_ERROR
            )
        );
    }

    /**
     * @return array
     */
    protected function getLastError()
    {
        return error_get_last();
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
    protected function translateMemoryLimit($memoryLimit)
    {
        $unit = strtolower(substr($memoryLimit, -1, 1));
        $memoryLimit = (int) $memoryLimit;
        switch ($unit) {
            case 'g':
                $memoryLimit *= 1024;
            // intentional
            case 'm':
                $memoryLimit *= 1024;
            // intentional
            case 'k':
                $memoryLimit *= 1024;
            // intentional
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

    /**
     * Provides ability to stub exits in unit tests
     */
    protected function terminate()
    {
        exit(1);
    }
}
