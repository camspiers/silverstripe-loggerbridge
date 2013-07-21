<?php

/**
 * Class LoggerBridge
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
     * @param \Psr\Log\LoggerInterface $logger
     * @param bool $showErrors
     */
    public function __construct(Psr\Log\LoggerInterface $logger, $showErrors = true)
    {
        $this->logger = $logger;
        $this->showErrors = $showErrors;
    }
    /**
     * @param $request
     * @param $session
     * @param $model
     * @return bool
     */
    public function preRequest($request, $session, $model)
    {
        if (!$this->registered) {
            $this->registerGlobalHandlers($request, $session, $model);
        }

        return true;
    }
    /**
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
    public function registerGlobalHandlers($request, $session, $model)
    {
        $that = $this;
        $this->errorHandler = set_error_handler(
            function ($errno, $errstr, $errfile, $errline) use ($that, $request, $session, $model) {
                $that->errorHandler(
                    $errno, $errstr, $errfile, $errline,
                    $request, $session, $model
                );
            }
        );
        $this->exceptionHandler = set_exception_handler(
            function (Exception $exception) use ($that, $request, $session, $model) {
                $that->exceptionHandler($exception, $request, $session, $model);
            }
        );
        register_shutdown_function(
            function () use ($that, $request, $session, $model) {
                $that->fatalHandler($request, $session, $model);
            }
        );
        $this->registered = true;
    }
    /**
     * Removes handlers we have added, and restores others if possible
     */
    public function deregisterGlobalHandlers()
    {
        set_error_handler($this->errorHandler);
        set_exception_handler($this->exceptionHandler);
        $this->registered = false;
    }
    /**
     * The general error handler that is added via set_error_handler
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     * @param $request
     * @param $session
     * @param $model
     * @return bool|string|void
     */
    public function errorHandler($errno, $errstr, $errfile, $errline, $request, $session, $model)
    {
        $context = array(
            'errfile' => $errfile,
            'errline' => $errline,
            'request' => print_r($request, true),
            'session' => print_r($session, true),
            'model'   => print_r($model, true)
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
     * @param Exception $exception
     * @param           $request
     * @param           $session
     * @param           $model
     * @return string|void
     */
    public function exceptionHandler(Exception $exception, $request, $session, $model)
    {
        $this->logger->error(
            $message = 'Uncaught ' . get_class($exception) . ': ' . $exception->getMessage(),
            $context = array(
                'exception' => $exception,
                'request'   => print_r($request, true),
                'session'   => print_r($session, true),
                'model'     => print_r($model, true)
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
     * Capture fatal errors
     */
    public function fatalHandler($request, $session, $model)
    {
        $error = error_get_last();
        if (
            $error
            &&
            in_array(
                $error['type'],
                array(
                    E_ERROR,
                    E_CORE_ERROR,
                    E_USER_ERROR
                )
            )
        ) {
            $this->logger->critical(
                $error['message'],
                array(
                    'errfile' => $error['file'],
                    'errline' => $error['line'],
                    'request' => print_r($request, true),
                    'session' => print_r($session, true),
                    'model'   => print_r($model, true)
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
