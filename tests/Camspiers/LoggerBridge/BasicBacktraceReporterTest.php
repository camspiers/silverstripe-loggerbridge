<?php

namespace Camspiers\LoggerBridge;

use Camspiers\LoggerBridge\BacktraceReporter\BasicBacktraceReporter;

class BasicBacktraceReporterTest extends \PHPUnit_Framework_TestCase
{
    public function testBacktraceLimitGet()
    {
        $reporter = new BasicBacktraceReporter();

        $this->assertEquals(0, $reporter->getBacktraceLimit());
    }

    public function testGetExceptionBacktrace()
    {
        $reporter = new BasicBacktraceReporter();

        $exception = new \Exception('Test');
        
        $backtrace = $exception->getTrace();

        foreach ($backtrace as $index => $backtraceCall) {
            unset($backtrace[$index]['args']);
        }
        
        $this->assertEquals($backtrace, $reporter->getBacktrace($exception));
    }

    public function testGetGlobalBacktrace()
    {
        $reporter = new BasicBacktraceReporter();

        $reporterBacktrace = $reporter->getBacktrace();

        array_shift($reporterBacktrace);

        $this->assertEquals(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), $reporterBacktrace);
    }

    public function testGetGlobalExceptionWithLimit()
    {
        $reporter = new BasicBacktraceReporter();
        $reporter->setBacktraceLimit(2);

        $exception = new \Exception('Test');

        $this->assertEquals(2, count($reporter->getBacktrace($exception)));
    }

    public function testGetGlobalBacktraceWithLimit()
    {
        $reporter = new BasicBacktraceReporter();
        $reporter->setBacktraceLimit(2);

        $this->assertEquals(2, count($reporter->getBacktrace()));
    }
}
