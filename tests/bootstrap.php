<?php

use Symfony\Component\ClassLoader\ClassMapGenerator;

$filename = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($filename)) {
    echo 'You must first install the vendors using composer.' . PHP_EOL;
    exit(1);
}

$loader = require_once $filename;
$loader->addClassMap(ClassMapGenerator::createMap(__DIR__ . '/../framework'));
