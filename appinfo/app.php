<?php

use OCA\EmlViewer\AppInfo\Application;

if ((@include_once __DIR__ . '/../vendor/autoload.php')===false) {
    throw new Exception('Cannot include autoload. Did you run install dependencies using composer?');
}

/** @var Application $app */
$app = \OC::$server->query(Application::class);
$app->register();
