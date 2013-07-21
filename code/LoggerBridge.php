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
     * @var null|callable
     */
    protected $errorHandler;
    /**
     * @var null|callable
     */
    protected $exceptionHandler;
    /**
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
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
        set_error_handler($this->exceptionHandler);
    }
    /**
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     * @param $request
     * @param $session
     * @param $model
     */
    public function errorHandler($errno, $errstr, $errfile, $errline, $request, $session, $model)
    {
        switch ($errno) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_USER_ERROR:
                $this->logger->error(
                    $errstr,
                    array(
                        'errfile' => $errfile,
                        'errline' => $errline,
                        'request' => print_r($request, true),
                        'session' => print_r($session, true),
                        'model'   => print_r($model, true)
                    )
                );
                break;

            case E_WARNING:
            case E_CORE_WARNING:
            case E_USER_WARNING:
                $this->logger->warning(
                    $errstr,
                    array(
                        'errfile' => $errfile,
                        'errline' => $errline,
                        'request' => print_r($request, true),
                        'session' => print_r($session, true),
                        'model'   => print_r($model, true)
                    )
                );
                break;

            case E_NOTICE:
            case E_USER_NOTICE:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
            case E_STRICT:
                $this->logger->notice(
                    $errstr,
                    array(
                        'errfile' => $errfile,
                        'errline' => $errline,
                        'request' => print_r($request, true),
                        'session' => print_r($session, true),
                        'model'   => print_r($model, true)
                    )
                );
                break;
        }
    }
    /**
     * @param Exception $exception
     * @param           $request
     * @param           $session
     * @param           $model
     */
    public function exceptionHandler(Exception $exception, $request, $session, $model)
    {
        $this->logger->error(
            $exception->getMessage(),
            array(
                'exception' => $exception,
                'request'   => print_r($request, true),
                'session'   => print_r($session, true),
                'model'     => print_r($model, true)
            )
        );
    }
    /**
     * Capture fatal errors
     */
    public function fatalHandler($request, $session, $model)
    {
        $error = error_get_last();
        if ($error) {
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
