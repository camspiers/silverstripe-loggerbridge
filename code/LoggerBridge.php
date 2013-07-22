<?php

/**
 * Class LoggerBridge
 * @author Cam Spiers <camspiers@gmail.com>
 */
class LoggerBridge
{
    /**
     * @var Psr\Log\LoggerInterface
     */
    protected $logger;
    /**
     * @var bool
     */
    protected $registered = false;
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
    protected $exceptionHandler;
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
        $this->showErrors = $showErrors;
    }
    /**
     * This hook function is executed from RequestProcessor before the request starts
     * @param $request
     * @param $session
     * @param $model
     * @return bool
     */
    public function preRequest($request, $session, $model)
    {
        if (!$this->registered) {
            $this->registerGlobalHandlers($request, $model);
        }

        return true;
    }
    /**
     * This hook function is executed from RequestProcessor after the request ends
     * @param $request
     * @param $session
     * @param $model
     * @return bool
     */
    public function postRequest($request, $session, $model)
    {
        if ($this->registered) {
            $this->deregisterGlobalHandlers();
        }

        return true;
    }
    /**
     * Registers global error handlers
     */
    public function registerGlobalHandlers($request, $model)
    {
        $this->request = $request;
        $this->model = $model;
        $this->errorHandler = set_error_handler(array($this, 'errorHandler'));
        $this->exceptionHandler = set_exception_handler(array($this, 'exceptionHandler'));
        register_shutdown_function(array($this, 'fatalHandler'));
        $this->registered = true;
    }
    /**
     * Removes handlers we have added, and restores others if possible
     */
    public function deregisterGlobalHandlers()
    {
        $this->request = null;
        $this->model = null;
        set_error_handler($this->errorHandler);
        set_exception_handler($this->exceptionHandler);
        $this->registered = false;
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
        $context = array(
            'errfile' => $errfile,
            'errline' => $errline,
            'request' => print_r($this->request, true),
            'model'   => print_r($this->model, true)
        );
        switch ($errno) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_USER_ERROR:
                $this->logger->error(
                    $errstr,
                    $context
                );
                if (Director::isDev() || Director::is_cli()) {
                    if ($this->showErrors) {
                        Debug::showError($errno, $errstr, $errfile, $errline, false, 'Error');
                    }
                } else {
                    Debug::friendlyError();
                }
                break;

            case E_WARNING:
            case E_CORE_WARNING:
            case E_USER_WARNING:
                $this->logger->warning(
                    $errstr,
                    $context
                );
                if ($this->showErrors && Director::isDev()) {
                    Debug::showError($errno, $errstr, $errfile, $errline, false, 'Warning');
                }
                break;

            case E_NOTICE:
            case E_USER_NOTICE:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
            case E_STRICT:
                $this->logger->notice(
                    $errstr,
                    $context
                );
                if ($this->showErrors && Director::isDev()) {
                    Debug::showError($errno, $errstr, $errfile, $errline, false, 'Notice');
                }
                break;
        }
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
                'errfile'   => $exception->getFile(),
                'errline'   => $exception->getLine(),
                'request'   => print_r($this->request, true),
                'model'     => print_r($this->model, true)
            )
        );

        if (Director::isDev()) {
            if ($this->showErrors) {
                Debug::showError(
                    E_USER_ERROR,
                    $message,
                    $exception->getFile(),
                    $exception->getLine(),
                    false,
                    'Error'
                );
            }
        } else {
            Debug::friendlyError();
        }
    }
    /**
     * Handles fatal errors
     */
    public function fatalHandler()
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
            $this->logger->critical(
                $error['message'],
                array(
                    'errfile' => $error['file'],
                    'errline' => $error['line'],
                    'request' => print_r($this->request, true),
                    'model'   => print_r($this->model, true)
                )
            );
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
     * @return boolean
     */
    public function getRegistered()
    {
        return $this->registered;
    }
}
