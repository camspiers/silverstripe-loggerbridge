<?php

namespace Camspiers\LoggerBridge;

/**
 * Class LoggerBridgeTest
 */
class LoggerBridgeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Set up the error_reporting before each tests
     */
    public function setUp()
    {
        error_reporting(E_ALL);
    }
    /**
     * 
     */
    public function tearDown()
    {
        ini_set('display_errors', true);
    }
    /**
     * Helper method to return a stub of a logger interface
     * @return \Psr\Log\LoggerInterface
     */
    public function getLoggerStub()
    {
        return $this->getMock('Psr\Log\LoggerInterface');
    }
    /**
     * Helper method to return a logger bridge
     * @param  null                     $methods
     * @param  \Psr\Log\LoggerInterface $logger
     * @param  bool                     $reportErrorsWhenNotLive
     * @param  null                     $reserveMemory
     * @return LoggerBridge
     */
    public function getLoggerBridge(
        $methods = null,
        $logger = null,
        $reportErrorsWhenNotLive = true,
        $reserveMemory = null
    ) {
        if ($methods !== null) {
            $bridge = $this->getMock(
                __NAMESPACE__ . '\\LoggerBridge',
                $methods,
                array(
                    $logger ? : $this->getLoggerStub(),
                    $reportErrorsWhenNotLive,
                    $reserveMemory
                )
            );
        } else {
            $bridge = new LoggerBridge(
                $logger ? : $this->getLoggerStub(),
                $reportErrorsWhenNotLive,
                $reserveMemory
            );
        }

        return $bridge;
    }
    /**
     * A helper method to get a mock that doesn't call its constructor
     * @param $class
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getMockWithoutConstructor($class)
    {
        return $this->getMock(
            $class,
            array(),
            array(),
            '',
            false
        );
    }
    /**
     * Test that there the default error reporter returns correctly
     */
    public function testGetErrorReporter()
    {
        $this->assertInstanceOf(
            __NAMESPACE__ . '\\ErrorReporter\\DebugErrorReporter',
            $this->getLoggerBridge()->getErrorReporter()
        );
    }
    /**
     * Test that there the default env reporter returns correctly
     */
    public function testEnvReporter()
    {
        $this->assertInstanceOf(
            __NAMESPACE__ . '\\EnvReporter\\DirectorEnvReporter',
            $this->getLoggerBridge()->getEnvReporter()
        );
    }
    /**
     * Tests that the translation of the reserve memory occurs correctly
     */
    public function testSetReserveMemory()
    {
        $bridge = $this->getLoggerBridge();
        $bridge->setReserveMemory(1024);
        $this->assertEquals(1024, $bridge->getReserveMemory());
        $bridge->setReserveMemory('2K');
        $this->assertEquals(2 * 1024, $bridge->getReserveMemory());
        $bridge->setReserveMemory('2M');
        $this->assertEquals(2 * 1024 * 1024, $bridge->getReserveMemory());
        $bridge->setReserveMemory('2G');
        $this->assertEquals(2 * 1024 * 1024 * 1024, $bridge->getReserveMemory());
    }
    /**
     * Tests the general function of register
     */
    public function testRegisterGlobalHandlersGeneral()
    {
        $env = $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter', array('isLive'));
        $bridge = $this->getLoggerBridge();
        $bridge->setEnvReporter($env);

        // Set expectations that isLive will be called once
        $env->expects($this->once())
            ->method('isLive')
            ->will($this->returnValue(true));

        // Check that things aren't registered
        $this->assertNull($bridge->getRegistered());

        // Do the registration
        $bridge->registerGlobalHandlers();

        // Check that things registered
        $this->assertTrue($bridge->getRegistered());
    }
    /**
     * Test that the actual error handlers are registered once
     */
    public function testRegisterGlobalHandlersOnce()
    {
        $env = $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter', array('isLive'));
        $bridge = $this->getLoggerBridge(
            array(
                'registerErrorHandler',
                'registerExceptionHandler',
                'registerFatalErrorHandler'
            )
        );
        $bridge->setEnvReporter($env);

        $bridge->expects($this->once())
            ->method('registerErrorHandler');

        $bridge->expects($this->once())
            ->method('registerExceptionHandler');

        $bridge->expects($this->once())
            ->method('registerFatalErrorHandler');

        $bridge->registerGlobalHandlers();
    }
    /**
     * Test that the actual error handlers are registered the correct number of times
     */
    public function testRegisterGlobalHandlersMultiple()
    {
        $env = $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter', array('isLive'));
        $bridge = $this->getLoggerBridge(
            array(
                'registerErrorHandler',
                'registerExceptionHandler',
                'registerFatalErrorHandler'
            )
        );
        $bridge->setEnvReporter($env);

        $bridge->expects($this->exactly(2))
            ->method('registerErrorHandler');

        $bridge->expects($this->exactly(2))
            ->method('registerExceptionHandler');

        // We only expect the fatal handler to be registered once
        $bridge->expects($this->once())
            ->method('registerFatalErrorHandler');

        $bridge->registerGlobalHandlers();
        $bridge->deregisterGlobalHandlers();
        $bridge->registerGlobalHandlers();
    }
    /**
     * Test that the actual error handlers are attached
     */
    public function testRegisterGlobalHandlersAttached()
    {
        $env = $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter', array('isLive'));
        $bridge = $this->getLoggerBridge();
        $bridge->setEnvReporter($env);

        $bridge->registerGlobalHandlers();

        $this->assertEquals(
            array(
                $bridge,
                'errorHandler'
            ),
            set_error_handler(
                function () {
                }
            )
        );

        $this->assertEquals(
            array(
                $bridge,
                'exceptionHandler'
            ),
            set_exception_handler(
                function () {
                }
            )
        );
    }
    /**
     * Test that memory is reserved
     */
    public function testRegisterGlobalHandlersMemoryReserved()
    {
        $env = $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter', array('isLive'));
        $bridge = $this->getLoggerBridge(
            array(
                'reserveMemory'
            ),
            null,
            true,
            '2M'
        );
        $bridge->setEnvReporter($env);

        $bridge->expects($this->once())
            ->method('reserveMemory');

        $bridge->registerGlobalHandlers();
    }
    /**
     *
     */
    public function testErrorHandlerError()
    {
        $bridge = $this->getLoggerBridge(
            null,
            $logger = $this->getLoggerStub()
        );

        $bridge->setEnvReporter(
            $env = $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter')
        );

        // isLive won't be called because reportErrorsWhenNotLive is true
        $env->expects($this->never())
            ->method('isLive')
            ->will($this->returnValue(true));

        $bridge->setErrorReporter(
            $reporter = $this->getMock(__NAMESPACE__ . '\\ErrorReporter\\ErrorReporter')
        );

        $reporter->expects($this->once())
            ->method('reportError');

        $logger->expects($this->once())
            ->method('error')
            ->with(
                $errstr = 'error string',
                array(
                    'errfile' => $errfile = 'somefile',
                    'errline' => $errline = 'someline',
                    'request' => '',
                    'model'   => ''
                )
            );

        $bridge->errorHandler(
            E_USER_ERROR,
            $errstr,
            $errfile,
            $errline
        );
    }
    /**
     * Tests how the error handler calls notice errors
     */
    public function testErrorHandlerNotice()
    {
        $bridge = $this->getLoggerBridge(
            null,
            $logger = $this->getLoggerStub()
        );

        $bridge->setEnvReporter(
            $env = $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter')
        );

        // isLive won't be called because reportErrorsWhenNotLive is true
        $env->expects($this->never())
            ->method('isLive')
            ->will($this->returnValue(true));

        $bridge->setErrorReporter(
            $reporter = $this->getMock(__NAMESPACE__ . '\\ErrorReporter\\ErrorReporter')
        );

        $reporter->expects($this->once())
            ->method('reportError');

        $logger->expects($this->once())
            ->method('notice')
            ->with(
                $errstr = 'error string',
                array(
                    'errfile' => $errfile = 'somefile',
                    'errline' => $errline = 'someline',
                    'request' => '',
                    'model'   => ''
                )
            );

        $bridge->errorHandler(
            E_USER_NOTICE,
            $errstr,
            $errfile,
            $errline
        );
    }
    /**
     * Tests how the error handler calls warning errors
     */
    public function testErrorHandlerWarning()
    {
        $bridge = $this->getLoggerBridge(
            null,
            $logger = $this->getLoggerStub()
        );

        $bridge->setEnvReporter(
            $env = $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter')
        );

        // isLive won't be called because reportErrorsWhenNotLive is true
        $env->expects($this->never())
            ->method('isLive')
            ->will($this->returnValue(true));

        $bridge->setErrorReporter(
            $reporter = $this->getMock(__NAMESPACE__ . '\\ErrorReporter\\ErrorReporter')
        );

        $reporter->expects($this->once())
            ->method('reportError');

        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $errstr = 'error string',
                array(
                    'errfile' => $errfile = 'somefile',
                    'errline' => $errline = 'someline',
                    'request' => '',
                    'model'   => ''
                )
            );

        $bridge->errorHandler(
            E_USER_WARNING,
            $errstr,
            $errfile,
            $errline
        );
    }
    /**
     * Tests error_reporting level
     */
    public function testErrorHandlerEnvironmentReporting()
    {
        // Set error reporting type
        error_reporting(E_ALL & ~E_USER_WARNING);

        $bridge = $this->getLoggerBridge(
            null,
            $logger = $this->getLoggerStub()
        );

        $bridge->setEnvReporter(
            $env = $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter')
        );

        $env->expects($this->never())
            ->method('isLive');

        $bridge->setErrorReporter(
            $reporter = $this->getMock(__NAMESPACE__ . '\\ErrorReporter\\ErrorReporter')
        );
        
        // We expect the report to never happen because the environment reporting is
        // E_ALL & ~E_USER_WARNING
        $reporter->expects($this->never())
            ->method('reportError');

        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $errstr = 'error string',
                array(
                    'errfile' => $errfile = 'somefile',
                    'errline' => $errline = 'someline',
                    'request' => '',
                    'model'   => ''
                )
            );

        $bridge->errorHandler(
            E_USER_WARNING,
            $errstr,
            $errfile,
            $errline
        );
    }
    public function testErrorHandlerSuppression()
    {
        // Set error reporting type
        error_reporting(0);

        $bridge = $this->getLoggerBridge(
            null,
            $logger = $this->getLoggerStub()
        );

        $bridge->setEnvReporter(
            $env = $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter')
        );

        $env->expects($this->never())
            ->method('isLive');

        $bridge->setErrorReporter(
            $reporter = $this->getMock(__NAMESPACE__ . '\\ErrorReporter\\ErrorReporter')
        );

        $reporter->expects($this->never())
            ->method('reportError');

        $logger->expects($this->never())
            ->method('warning');

        $bridge->errorHandler(
            E_USER_WARNING,
            '',
            '',
            ''
        );
    }
    /**
     * Tests error reporting we not live and we request no display
     */
    public function testErrorHandlerNotLiveNoDisplay()
    {
        $bridge = $this->getLoggerBridge(
            null,
            $logger = $this->getLoggerStub(),
            false
        );

        $bridge->setEnvReporter(
            $env = $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter')
        );

        $env->expects($this->once())
            ->method('isLive')
            ->will($this->returnValue(false));

        $bridge->setErrorReporter(
            $reporter = $this->getMock(__NAMESPACE__ . '\\ErrorReporter\\ErrorReporter')
        );

        // We expect the report to never happen because we are not live && reportErrorsWhenNotLive is false
        $reporter->expects($this->never())
            ->method('reportError');

        $logger->expects($this->once())
            ->method('warning');

        $bridge->errorHandler(
            E_USER_WARNING,
            '',
            '',
            ''
        );
    }
    
    public function testFatalErrorHandlerNotRegistered()
    {
        $bridge = $this->getLoggerBridge(
            array(
                'getRegistered',
                'restoreMemory'
            ),
            $logger = $this->getLoggerStub()
        );

        $bridge->setEnvReporter(
            $env = $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter')
        );

        $env->expects($this->never())
            ->method('isLive');

        $bridge->setErrorReporter(
            $reporter = $this->getMock(__NAMESPACE__ . '\\ErrorReporter\\ErrorReporter')
        );

        $bridge->expects($this->once())
            ->method('getRegistered')
            ->will($this->returnValue(false));

        // We expect the log to never happen
        $logger->expects($this->never())
            ->method('critical');

        // We expect the report to never happen
        $reporter->expects($this->never())
            ->method('reportError');

        $bridge->fatalHandler();
    }

    public function testFatalErrorHandlerNotFatal()
    {
        $bridge = $this->getLoggerBridge(
            array(
                'getRegistered',
                'getLastErrorFatal',
                'restoreMemory'
            ),
            $logger = $this->getLoggerStub()
        );

        $bridge->setEnvReporter(
            $env = $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter')
        );

        $env->expects($this->never())
            ->method('isLive');

        $bridge->setErrorReporter(
            $reporter = $this->getMock(__NAMESPACE__ . '\\ErrorReporter\\ErrorReporter')
        );

        $bridge->expects($this->once())
            ->method('getRegistered')
            ->will($this->returnValue(true));

        $bridge->expects($this->once())
            ->method('getLastErrorFatal')
            ->will($this->returnValue(false));

        // We expect the log to never happen
        $logger->expects($this->never())
            ->method('critical');

        // We expect the report to never happen
        $reporter->expects($this->never())
            ->method('reportError');

        $bridge->fatalHandler();
    }

    public function testFatalErrorHandlerNoReport()
    {
        $bridge = $this->getLoggerBridge(
            array(
                'getRegistered',
                'getLastErrorFatal',
                'restoreMemory'
            ),
            $logger = $this->getLoggerStub(),
            false
        );

        $bridge->setEnvReporter(
            $env = $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter')
        );

        $env->expects($this->once())
            ->method('isLive')
            ->will($this->returnValue(false));

        $bridge->setErrorReporter(
            $reporter = $this->getMock(__NAMESPACE__ . '\\ErrorReporter\\ErrorReporter')
        );

        $bridge->expects($this->once())
            ->method('getRegistered')
            ->will($this->returnValue(true));

        $bridge->expects($this->once())
            ->method('getLastErrorFatal')
            ->will(
                $this->returnValue(
                    array(
                        'message' => 'Test',
                        'file'    => 'file',
                        'line'    => 'line'
                    )
                )
            );

        // We expect the log to never happen
        $logger->expects($this->once())
            ->method('critical')
            ->with(
                'Test',
                array(
                    'errfile' => 'file',
                    'errline' => 'line'
                )
            );

        // We expect the report to never happen
        $reporter->expects($this->never())
            ->method('reportError');

        $bridge->fatalHandler();
    }

    public function testFatalErrorHandlerReportDueToLive()
    {
        $bridge = $this->getLoggerBridge(
            array(
                'getRegistered',
                'getLastErrorFatal',
                'restoreMemory'
            ),
            $logger = $this->getLoggerStub(),
            false
        );

        $bridge->setEnvReporter(
            $env = $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter')
        );

        $env->expects($this->once())
            ->method('isLive')
            ->will($this->returnValue(true));

        $bridge->setErrorReporter(
            $reporter = $this->getMock(__NAMESPACE__ . '\\ErrorReporter\\ErrorReporter')
        );

        $bridge->expects($this->once())
            ->method('getRegistered')
            ->will($this->returnValue(true));

        $bridge->expects($this->once())
            ->method('getLastErrorFatal')
            ->will(
                $this->returnValue(
                    array(
                        'message' => 'Test',
                        'file'    => 'file',
                        'line'    => 'line'
                    )
                )
            );

        // We expect the log to never happen
        $logger->expects($this->once())
            ->method('critical')
            ->with(
                'Test',
                array(
                    'errfile' => 'file',
                    'errline' => 'line'
                )
            );

        // We expect the report to never happen
        $reporter->expects($this->once())
            ->method('reportError');

        $bridge->fatalHandler();
    }

    public function testFatalErrorHandlerReport()
    {
        $bridge = $this->getLoggerBridge(
            array(
                'getRegistered',
                'getLastErrorFatal',
                'restoreMemory'
            ),
            $logger = $this->getLoggerStub()
        );

        $bridge->setEnvReporter(
            $env = $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter')
        );

        $env->expects($this->never())
            ->method('isLive');

        $bridge->setErrorReporter(
            $reporter = $this->getMock(__NAMESPACE__ . '\\ErrorReporter\\ErrorReporter')
        );

        $bridge->expects($this->once())
            ->method('getRegistered')
            ->will($this->returnValue(true));

        $bridge->expects($this->once())
            ->method('getLastErrorFatal')
            ->will(
                $this->returnValue(
                    array(
                        'message' => 'Test',
                        'file'    => 'file',
                        'line'    => 'line'
                    )
                )
            );

        // We expect the log to never happen
        $logger->expects($this->once())
            ->method('critical')
            ->with(
                'Test',
                array(
                    'errfile' => 'file',
                    'errline' => 'line'
                )
            );

        // We expect the report to never happen
        $reporter->expects($this->once())
            ->method('reportError');

        $bridge->fatalHandler();
    }

    public function testFatalErrorHandlerPreserveMemory()
    {
        $bridge = $this->getLoggerBridge(
            array(
                'getRegistered',
                'getLastErrorFatal',
                'restoreMemory'
            )
        );
        
        $bridge->setReserveMemory('2M');

        $bridge->setEnvReporter(
            $env = $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter')
        );

        $bridge->setErrorReporter(
            $reporter = $this->getMock(__NAMESPACE__ . '\\ErrorReporter\\ErrorReporter')
        );

        $bridge->expects($this->once())
            ->method('getRegistered')
            ->will($this->returnValue(true));

        $bridge->expects($this->once())
            ->method('getLastErrorFatal')
            ->will(
                $this->returnValue(
                    array(
                        'message' => 'Test',
                        'file'    => 'file',
                        'line'    => 'line'
                    )
                )
            );
        
        $bridge->expects($this->once())
            ->method('restoreMemory');

        $bridge->fatalHandler();
    }
    
    public function testExceptionHandler()
    {
        $bridge = $this->getLoggerBridge(
            null,
            $logger = $this->getLoggerStub()
        );

        $bridge->setEnvReporter(
            $env = $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter')
        );

        $env->expects($this->never())
            ->method('isLive');

        $bridge->setErrorReporter(
            $reporter = $this->getMock(__NAMESPACE__ . '\\ErrorReporter\\ErrorReporter')
        );

        $reporter->expects($this->once())
            ->method('reportError');
        
        $exception = new \Exception('Message');
        
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Uncaught Exception: Message',
                array(
                    'errfile' => $exception->getFile(),
                    'errline' => $exception->getLine(),
                    'request' => '',
                    'model'   => ''
                )
            );

        $bridge->exceptionHandler($exception);
    }

    public function testExceptionHandlerNoReport()
    {
        $bridge = $this->getLoggerBridge(
            null,
            null,
            false
        );

        $bridge->setEnvReporter(
            $env = $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter')
        );

        $env->expects($this->once())
            ->method('isLive')
            ->will($this->returnValue(false));

        $bridge->setErrorReporter(
            $reporter = $this->getMock(__NAMESPACE__ . '\\ErrorReporter\\ErrorReporter')
        );

        $reporter->expects($this->never())
            ->method('reportError');

        $bridge->exceptionHandler(new \Exception('Message'));
    }
    
    public function testPreRequest()
    {
        $bridge = $this->getLoggerBridge(
            array(
                'registerGlobalHandlers'
            )
        );

        $bridge->setEnvReporter(
            $this->getMock(__NAMESPACE__ . '\\EnvReporter\\EnvReporter')
        );

        $bridge->setErrorReporter(
            $this->getMock(__NAMESPACE__ . '\\ErrorReporter\\ErrorReporter')
        );
        
        $bridge->expects($this->once())
            ->method('registerGlobalHandlers')
            ->with($request = $this->getMockWithoutConstructor('SS_HTTPRequest'), $model = $this->getMockWithoutConstructor('DataModel'));
        
        $this->assertTrue(
            $bridge->preRequest($request, $this->getMockWithoutConstructor('Session'), $model)
        );
    }
}
