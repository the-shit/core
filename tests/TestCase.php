<?php

namespace Tests;

use Illuminate\Foundation\Application;
use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        // Set default config values for testing
        config(['conduit.user_agent' => 'human']);

        return $app;
    }

    /**
     * Clean up test environment
     */
    protected function tearDown(): void
    {
        // Clean up any test artifacts
        if (file_exists(storage_path('events.jsonl'))) {
            unlink(storage_path('events.jsonl'));
        }

        // Reset global state
        if (isset($GLOBALS['argv'])) {
            unset($GLOBALS['argv']);
        }

        parent::tearDown();
    }
}
