<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class CleanupServiceProvider extends ServiceProvider
{
    /**
     * Commands to hide from the list
     */
    protected $hiddenCommands = [
        // Laravel make commands
        'make:command',
        'make:factory',
        'make:migration',
        'make:model',
        'make:seeder',
        'make:test',

        // Migration commands - internal database operations
        'migrate',
        'migrate:fresh',
        'migrate:install',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'migrate:status',

        // DB commands
        'db:seed',
        'db:wipe',

        // App commands - internal build tools
        'app:build',
        'app:install',
        'app:rename',

        // Test/debug commands - developer tools
        'test:human-ai',
        'debug:detection',
        'brain:test',

        // Only hide truly redundant/internal commands
        'ai:models',     // List command - use ai:setup instead
        'ai:stream',     // Toggle - rarely used

        // Orchestrator internal commands
        'orchestrator:activity',
        'orchestrator:sync',

        // Brain duplicates (keep ai:* versions for discovery)
        'brain:analyze',   // Duplicate of ai:analyze
        'brain:commit',    // Duplicate of ai:commit
        'brain:fix',       // Duplicate of ai:fix
        'brain:providers', // Internal config

        // Claude wrapper internals
        'claude:conflicts',
        'claude:intercept',

        // Component scaffolding - developer tools
        'component:config',
        'component:scaffold',
    ];

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->app->booted(function () {
                $artisan = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);

                foreach ($this->hiddenCommands as $commandName) {
                    if (isset($artisan->all()[$commandName])) {
                        $artisan->all()[$commandName]->setHidden(true);
                    }
                }
            });
        }
    }
}
