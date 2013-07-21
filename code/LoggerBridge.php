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
        $this->registerGlobalHandlers();

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
        return true;
    }
    /**
     * Registers global error handlers
     */
    public function registerGlobalHandlers()
    {
        set_error_handler(array($this, 'errorHandler'));
        set_exception_handler(array($this, 'exceptionHandler'));
        register_shutdown_function(array($this, 'fatalHandler'));
    }
    /**
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     * @return bool|string|void
     */
    public function errorHandler($errno, $errstr, $errfile, $errline)
    {
        switch ($errno) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_USER_ERROR:
                $this->logger->error(
                    $errstr,
                    array(
                        'errfile' => $errfile,
                        'errline' => $errline
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
                        'errline' => $errline
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
                        'errline' => $errline
                    )
                );
                break;
        }
    }
    /**
     * @param Exception $exception
     */
    public function exceptionHandler(Exception $exception)
    {
        $this->logger->error($exception->getMessage(), array('exception' => $exception));
    }
    /**
     * Capture fatal errors
     */
    public function fatalHandler()
    {
        $error = error_get_last();
        if ($error) {
            $this->logger->critical(
                $error['message'],
                array(
                    'errfile' => $error['file'],
                    'errline' => $error['line']
                )
            );
        }
    }
}
