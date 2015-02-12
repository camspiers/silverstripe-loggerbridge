<?php

use Symfony\Component\ClassLoader\ClassMapGenerator;

define('FRAMEWORK_PATH', dirname(__DIR__) . '/framework');

$filename = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($filename)) {
    echo 'You must first install the vendors using composer.' . PHP_EOL;
    exit(1);
}

$loader = require $filename;

$frameworkClassMap = ClassMapGenerator::createMap(dirname(__DIR__) . '/framework');
unset($frameworkClassMap['PHPUnit_Framework_TestCase']);

$loader->addClassMap($frameworkClassMap);
