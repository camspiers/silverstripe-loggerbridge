<?php

$injector = Injector::inst();

if ($injector->hasService('RequestProcessor')) {
    $injector->updateSpec('RequestProcessor', 'filters', '%$LoggerBridge');
} else {
    $injector->load(
        array(
            'RequestProcessor' => array(
                'class' => 'RequestProcessor',
                'constructor' => array(
                    0 => array(
                        '%$LoggerBridge'
                    )
                )
            )
        )
    );
}
