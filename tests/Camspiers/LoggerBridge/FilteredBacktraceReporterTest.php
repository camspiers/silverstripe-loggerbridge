<?php

namespace Camspiers\LoggerBridge;

use Camspiers\LoggerBridge\BacktraceReporter\FilteredBacktraceReporter;

class FilteredBacktraceReporterTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateObject()
    {
        $reporter = new FilteredBacktraceReporter(array());

        $this->assertInstanceOf('Camspiers\LoggerBridge\BacktraceReporter\FilteredBacktraceReporter', $reporter);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testCreateObjectException()
    {
        new FilteredBacktraceReporter("test");
    }

    public function testFilteredFunctionsBacktrace()
    {
        $reporter = new FilteredBacktraceReporter(array());

        $exception = new \Exception('Test');

        $backtrace = $exception->getTrace();

        foreach ($backtrace as $index => $backtraceCall) {
            unset($backtrace[$index]['args']);
        }

        $this->assertEquals($backtrace, $reporter->getBacktrace($exception));

        $reporter = new FilteredBacktraceReporter(
            $fns = array(
                str_replace('\\', '\\\\', __CLASS__ . '->' . __FUNCTION__)
            )
        );

        $this->assertEquals(count($exception->getTrace()) - 1, count($reporter->getBacktrace($exception)));
    }
}
