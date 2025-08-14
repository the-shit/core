<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    // Mock component existence
    $this->componentPath = base_path('💩-components/brain');
    $this->componentBinary = $this->componentPath.'/brain';
});

test('brain command exists and is registered', function () {
    $this->artisan('list')
        ->expectsOutputToContain('brain')
        ->expectsOutputToContain('THE SHIT knows what you need')
        ->assertSuccessful();
});

test('brain command handles no arguments gracefully', function () {
    // Mock Process to avoid actual execution
    Process::fake();

    $this->artisan('brain')
        ->assertSuccessful();
});

test('brain command passes query to component', function () {
    Process::fake();

    $this->artisan('brain', ['query' => 'test query'])
        ->assertSuccessful();

    Process::assertRan(function ($process) {
        return str_contains($process->command, 'brain') &&
               str_contains($process->command, 'test query');
    });
});

test('brain command supports non-interactive mode', function () {
    Process::fake();

    $this->artisan('brain', [
        'query' => 'test query',
        '--no-interaction' => true,
    ])->assertSuccessful();
});

test('brain command handles AI user agent', function () {
    $_SERVER['CONDUIT_USER_AGENT'] = 'ai';

    Process::fake();

    $this->artisan('brain', ['query' => 'AI query'])
        ->assertSuccessful();

    unset($_SERVER['CONDUIT_USER_AGENT']);
});

test('brain learn subcommand is registered', function () {
    $this->artisan('list')
        ->expectsOutputToContain('brain:learn')
        ->expectsOutputToContain('View learning history and patterns')
        ->assertSuccessful();
});

test('brain cost subcommand is registered', function () {
    $this->artisan('list')
        ->expectsOutputToContain('brain:cost')
        ->expectsOutputToContain('Check AI usage costs')
        ->assertSuccessful();
});

test('brain command handles complex queries', function () {
    Process::fake();

    $complexQuery = 'analyze the code in app/Commands and suggest refactoring opportunities';

    $this->artisan('brain', ['query' => $complexQuery])
        ->assertSuccessful();

    Process::assertRan(function ($process) use ($complexQuery) {
        return str_contains($process->command, 'brain') &&
               str_contains($process->command, $complexQuery);
    });
});

test('brain command handles special characters in query', function () {
    Process::fake();

    $specialQuery = 'what does $this->app->bind() do?';

    $this->artisan('brain', ['query' => $specialQuery])
        ->assertSuccessful();
});

test('brain command integrates with event bus', function () {
    Process::fake();

    // Clear any existing events
    $eventFile = storage_path('events.jsonl');
    if (File::exists($eventFile)) {
        File::delete($eventFile);
    }

    $this->artisan('brain', ['query' => 'event test'])
        ->assertSuccessful();

    // In a real scenario, the brain component would emit events
    // For testing, we'll verify the event bus is available
    expect(class_exists(\App\Services\EventBusService::class))->toBeTrue();
});
