<?php

/**
 * Enables global SilverStripe logging with a PSR-3 logger like Monolog.
 * 
 * The logger is attached in by using a RequestProcessor filter. This behaviour is required
 * so the logger is attached after the environment only and except rules in yml are applied.
 * 
 * @author Cam Spiers <camspiers@gmail.com>
 */
class LoggerBridge implements RequestFilter
{
    /**
     * @var Psr\Log\LoggerInterface
     */
    protected $logger;
    /**
     * @var bool|null
     */
    protected $registered;
    /**
     * @var bool
     */
    protected $showErrors = true;
    /**
     * @var null|callable
     */
    protected $errorHandler;
    /**
     * @var null|callable
     */
    protected $exceptionHandler
    /**
     * @var SS_HTTPRequest
     */
    protected $request;
    /**
     * @var DataModel
     */
    protected $model;
    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param bool                     $showErrors If false stops the display of SilverStripe errors
     */
    public function __construct(Psr\Log\LoggerInterface $logger, $showErrors = true)
    {
        $this->logger = $logger;
        $this->showErrors = (bool) $showErrors;
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
     * @return boolean
     */
    public function getRegistered()
    {
        return $this->registered;
    }
    /**
     * This hook function is executed from RequestProcessor before the request starts
     * @param SS_HTTPRequest $request
     * @param Session        $session
     * @param DataModel      $model
     * @return bool
     */
    public function preRequest(SS_HTTPRequest $request, Session $session, DataModel $model)
    {
        $this->registerGlobalHandlers($request, $model);

        return true;
    }
    /**
     * This hook function is executed from RequestProcessor after the request ends
     * @param SS_HTTPRequest  $request
     * @param SS_HTTPResponse $response
     * @param DataModel       $model
     * @return bool
     */
    public function postRequest(SS_HTTPRequest $request, SS_HTTPResponse $response, DataModel $model)
    {
        $this->deregisterGlobalHandlers();

        return true;
    }
    /**
     * Registers global error handlers
     * @param SS_HTTPRequest $request
     * @param DataModel      $model
     */
    public function registerGlobalHandlers(SS_HTTPRequest $request = null, DataModel $model = null)
    {
        if (!$this->registered) {
            // If the developer wants to see errors in dev mode then don't let php display them
            if (Director::isDev()) {
                ini_set('display_errors', !$this->showErrors);
            }
            $this->request = $request;
            $this->model = $model;
            // Store the previous error handler if there was any
            $this->errorHandler = set_error_handler(array($this, 'errorHandler'));
            // Store the previous exception handler if there was any
            $this->exceptionHandler = set_exception_handler(array($this, 'exceptionHandler'));
            // If the shutdown function hasn't been registered register it
            if ($this->registered === null) {
                register_shutdown_function(array($this, 'fatalHandler'));
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
            // Restore the previous error handler
            set_error_handler($this->errorHandler);
            // Restore the previous exception handler
            set_exception_handler($this->exceptionHandler);
            $this->registered = false;
        }
    }
    /**
     * Handlers general errors, user, warn and notice
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     * @return bool|string|void
     */
    public function errorHandler($errno, $errstr, $errfile, $errline)
    {
        $alwaysShowError = false;
        switch ($errno) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_USER_ERROR:
                $errorType = 'error';
                $alwaysShowError = true;
                break;

            case E_WARNING:
            case E_CORE_WARNING:
            case E_USER_WARNING:
                $errorType = 'warning';
                break;

            case E_NOTICE:
            case E_USER_NOTICE:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
            case E_STRICT:
                $errorType = 'notice';
                break;

            default:
                $errorType = 'error';
        }
        $this->logger->$errorType(
            $errstr,
            array(
                'errfile' => $errfile,
                'errline' => $errline,
                'request' => print_r($this->request, true),
                'model'   => print_r($this->model, true)
            )
        );
        $this->displayError($alwaysShowError, $errno, $errstr, $errfile, $errline, strtoupper($errorType));
    }
    /**
     * Handles uncaught exceptions
     * @param  Exception   $exception
     * @return string|void
     */
    public function exceptionHandler(Exception $exception)
    {
        $this->logger->error(
            $message = 'Uncaught ' . get_class($exception) . ': ' . $exception->getMessage(),
            $context = array(
                'errfile' => $exception->getFile(),
                'errline' => $exception->getLine(),
                'request' => print_r($this->request, true),
                'model'   => print_r($this->model, true)
            )
        );

        $this->displayError(
            true,
            E_USER_ERROR,
            $message,
            $exception->getFile(),
            $exception->getLine(),
            'Error'
        );
    }
    /**
     * Handles fatal errors
     * If we are registered, and there is a fatal error then log and try to gracefully handle error output
     */
    public function fatalHandler()
    {
        $error = error_get_last();
        if (
            $this->registered
            &&
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
            $this->logger->critical(
                $error['message'],
                array(
                    'errfile' => $error['file'],
                    'errline' => $error['line'],
                    'request' => print_r($this->request, true),
                    'model'   => print_r($this->model, true)
                )
            );
            
            $this->displayError(
                true,
                E_CORE_ERROR,
                $error['message'],
                $error['file'],
                $error['line'],
                'Fatal Error'
            );
        }
    }
    /**
     * @param $always
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     * @param $errtype
     */
    protected function displayError($always, $errno, $errstr, $errfile, $errline, $errtype)
    {
        if (Director::isDev()) {
            if ($this->showErrors) {
                Debug::showError(
                    $errno,
                    $errstr,
                    $errfile,
                    $errline,
                    false,
                    $errtype
                );
            }
        } elseif($always) {
            Debug::friendlyError();
        }
    }
}
