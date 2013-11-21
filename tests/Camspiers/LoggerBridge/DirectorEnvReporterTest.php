<?php

namespace Camspiers\LoggerBridge;

use Config;
use Camspiers\LoggerBridge\EnvReporter\DirectorEnvReporter;

class DirectorEnvReporterTest extends \PHPUnit_Framework_TestCase
{
    public function testIsLive()
    {
        Config::inst()->update('Director', 'environment_type', 'dev');

        $reporter = new DirectorEnvReporter();

        $this->assertEquals(false, $reporter->isLive());

        Config::inst()->update('Director', 'environment_type', 'test');

        $this->assertEquals(false, $reporter->isLive());

        Config::inst()->update('Director', 'environment_type', 'live');

        $this->assertEquals(true, $reporter->isLive());
    }
}
