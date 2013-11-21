<?php

namespace Camspiers\LoggerBridge;

use Camspiers\LoggerBridge\ErrorReporter\DebugErrorReporter;
use Config;

class DebugErrorReporterTest extends \PHPUnit_Framework_TestCase
{
    public function testReportErrorNotLive()
    {
        Config::inst()->update('SS_Backtrace', 'ignore_function_args', array());

        $envMock = $this->getMock(__NAMESPACE__.'\EnvReporter\EnvReporter');

        $envMock->expects($this->once())
            ->method('isLive')
            ->will($this->returnValue(false));

        $debugErrorReporter = new DebugErrorReporter($envMock);

        ob_start();

        $debugErrorReporter->reportError(
            new \ErrorException(
                'Error message',
                E_USER_ERROR,
                0,
                'example-file.php',
                10
            )
        );

        $contents = ob_get_contents();
        ob_end_clean();

        $this->assertContains(
            '[User Error]',
            $contents
        );

        $this->assertContains(
            'Error message',
            $contents
        );

        $this->assertContains(
            'Line 10 in example-file.php',
            $contents
        );
    }

    public function testReportErrorLive()
    {
        Config::inst()->update('Director', 'alternate_base_url', 'http://localhost');
        define('BASE_URL', 'http://localhost/');
        define('FRAMEWORK_DIR', 'framework');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        Config::inst()->update('SS_Backtrace', 'ignore_function_args', array());

        $envMock = $this->getMock(__NAMESPACE__.'\EnvReporter\EnvReporter');

        $envMock->expects($this->once())
            ->method('isLive')
            ->will($this->returnValue(true));

        $debugErrorReporter = new DebugErrorReporter($envMock);

        ob_start();

        $debugErrorReporter->reportError(
            new \ErrorException(
                'Error message',
                E_USER_ERROR,
                0,
                'example-file.php',
                10
            )
        );

        $contents = ob_get_contents();
        ob_end_clean();

        $this->assertEquals(
            <<<HTML
<!DOCTYPE html><html><head><title>GET /</title><link rel="stylesheet" type="text/css" href="http://localhost/framework/css/debug.css" /></head><body><div class="info"><h1>Website Error</h1></div></body></html>
HTML
            ,
            $contents
        );
    }
}
