<?php

namespace Camspiers\LoggerBridge\EnvReporter;

/**
 * Class EnvReporter
 * @package Camspiers\LoggerBridge\EnvReporter
 */
interface EnvReporter
{
    /**
     * @return mixed
     */
    public function isLive();
}
