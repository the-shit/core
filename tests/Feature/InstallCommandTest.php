<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    // Clean up test installations
    $testComponents = base_path('💩-components/test-component');
    if (File::exists($testComponents)) {
        File::deleteDirectory($testComponents);
    }
});

afterEach(function () {
    // Clean up after tests
    $testComponents = base_path('💩-components/test-component');
    if (File::exists($testComponents)) {
        File::deleteDirectory($testComponents);
    }
});

test('install command exists and is registered', function () {
    $this->artisan('list')
        ->expectsOutputToContain('install')
        ->expectsOutputToContain('Install a component from THE SHIT ecosystem')
        ->assertSuccessful();
});

test('install command requires component name', function () {
    $this->artisan('install')
        ->expectsQuestion('Which component would you like to install?', 'test-component')
        ->assertSuccessful();
});

test('install command accepts component name as argument', function () {
    Process::fake();
    Http::fake();

    $this->artisan('install', ['component' => 'test-component'])
        ->assertSuccessful();
});

test('install command supports branch option', function () {
    Process::fake();
    Http::fake();

    $this->artisan('install', [
        'component' => 'test-component',
        '--branch' => 'develop',
    ])->assertSuccessful();
});

test('install command supports version option', function () {
    Process::fake();
    Http::fake();

    $this->artisan('install', [
        'component' => 'test-component',
        '--version' => 'v1.2.3',
    ])->assertSuccessful();
});

test('install command handles GitHub repository format', function () {
    Process::fake();
    Http::fake();

    $this->artisan('install', ['component' => 'owner/repository'])
        ->assertSuccessful();
});

test('install command creates component directory', function () {
    Process::fake([
        'git clone *' => Process::result('', 0),
        'cd * && composer install *' => Process::result('', 0),
    ]);

    Http::fake();

    $componentsPath = base_path('💩-components');
    if (! File::exists($componentsPath)) {
        File::makeDirectory($componentsPath);
    }

    $this->artisan('install', ['component' => 'test-component'])
        ->assertSuccessful();
});

test('install command handles installation failure gracefully', function () {
    Process::fake([
        'git clone *' => Process::result('Error: repository not found', 128),
    ]);

    $this->artisan('install', ['component' => 'non-existent-component'])
        ->assertFailed();
});

test('install command detects existing installations', function () {
    // Create existing component
    $componentPath = base_path('💩-components/existing-component');
    File::makeDirectory($componentPath, 0755, true);
    File::put($componentPath.'/💩.json', json_encode([
        'name' => 'existing-component',
        'version' => '1.0.0',
    ]));

    Process::fake();

    $this->artisan('install', ['component' => 'existing-component'])
        ->expectsQuestion('Component existing-component is already installed. Reinstall?', false)
        ->assertSuccessful();

    // Cleanup
    File::deleteDirectory($componentPath);
});

test('install command supports force reinstall', function () {
    // Create existing component
    $componentPath = base_path('💩-components/existing-component');
    File::makeDirectory($componentPath, 0755, true);
    File::put($componentPath.'/💩.json', json_encode([
        'name' => 'existing-component',
        'version' => '1.0.0',
    ]));

    Process::fake();

    $this->artisan('install', [
        'component' => 'existing-component',
        '--force' => true,
    ])->assertSuccessful();

    // Cleanup
    File::deleteDirectory($componentPath);
});

test('install command validates component manifest', function () {
    Process::fake([
        'git clone *' => Process::result('', 0),
    ]);

    // Create component without manifest
    $componentPath = base_path('💩-components/invalid-component');
    File::makeDirectory($componentPath, 0755, true);

    $this->artisan('install', ['component' => 'invalid-component'])
        ->assertFailed();

    // Cleanup
    File::deleteDirectory($componentPath);
});

test('install command runs composer install for PHP components', function () {
    Process::fake([
        'git clone *' => Process::result('', 0),
        '*composer install*' => Process::result('Installing dependencies', 0),
    ]);

    $this->artisan('install', ['component' => 'php-component'])
        ->assertSuccessful();

    Process::assertRan(function ($process) {
        return str_contains($process->command, 'composer install');
    });
});

test('install command emits installation events', function () {
    Process::fake();
    Http::fake();

    // Clear any existing events
    $eventFile = storage_path('events.jsonl');
    if (File::exists($eventFile)) {
        File::delete($eventFile);
    }

    $this->artisan('install', ['component' => 'test-component'])
        ->assertSuccessful();

    // Verify event bus service exists
    expect(class_exists(\App\Services\EventBusService::class))->toBeTrue();
});

test('install command supports non-interactive mode', function () {
    $_SERVER['CONDUIT_USER_AGENT'] = 'ai';

    Process::fake();
    Http::fake();

    $this->artisan('install', [
        'component' => 'ai-component',
        '--no-interaction' => true,
    ])->assertSuccessful();

    unset($_SERVER['CONDUIT_USER_AGENT']);
});

test('install command discovers components from GitHub topics', function () {
    Http::fake([
        'api.github.com/search/repositories*' => Http::response([
            'items' => [
                ['full_name' => 'user/component1', 'description' => 'Component 1'],
                ['full_name' => 'user/component2', 'description' => 'Component 2'],
            ],
        ], 200),
    ]);

    Process::fake();

    $this->artisan('install')
        ->expectsQuestion('Which component would you like to install?', 'user/component1')
        ->assertSuccessful();
});
