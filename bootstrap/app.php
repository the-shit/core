<?php

use LaravelZero\Framework\Application;

$app = Application::configure(basePath: dirname(__DIR__))->create();

// Bind storage path for Laravel Zero compatibility
$app->useStoragePath(dirname(__DIR__).'/storage');

return $app;
