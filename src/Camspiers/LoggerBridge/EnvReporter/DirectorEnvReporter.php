<?php

namespace Camspiers\LoggerBridge\EnvReporter;

use Director;

/**
 * Class DirectorEnvReporter
 */
class DirectorEnvReporter implements EnvReporter
{
    /**
     * Returns whether we are in a live environment
     * @return bool
     */
    public function isLive()
    {
        return Director::isLive();
    }
}
