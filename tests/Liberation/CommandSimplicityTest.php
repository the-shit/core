<?php

namespace Tests\Liberation;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Tests that validate THE SHIT's liberation from complexity
 * Commands must be simple, single-purpose, and configuration-free
 */
class CommandSimplicityTest extends TestCase {}

test('every command does exactly one thing', function () {
    $commands = Artisan::all();

    foreach ($commands as $name => $command) {
        // Skip Laravel internal commands
        if (str_starts_with($name, 'make:') || str_starts_with($name, 'migrate')) {
            continue;
        }

        $definition = $command->getDefinition();
        $arguments = $definition->getArguments();

        // Liberation rule: Maximum one argument
        expect(count($arguments))->toBeLessThanOrEqual(2); // 'command' is always first

        // Liberation rule: No complex options except global ones
        $options = $definition->getOptions();
        $allowedGlobalOptions = ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'json', 'env'];

        foreach ($options as $option) {
            $optionName = $option->getName();
            expect($allowedGlobalOptions)->toContain($optionName,
                "Command {$name} has non-global option: {$optionName}. This violates liberation from complexity.");
        }
    }
});

test('commands use smart defaults instead of configuration', function () {
    // Test that install command works without any configuration
    $this->artisan('install', ['component' => 'test-component'])
        ->doesntExpectOutput('Configuration required')
        ->doesntExpectOutput('Missing required option');

    // Test that brain command works without configuration
    $this->artisan('brain', ['query' => 'test query'])
        ->doesntExpectOutput('Configuration required')
        ->doesntExpectOutput('Missing required option');
});

test('no command requires more than one user input', function () {
    $commands = [
        'brain' => ['query' => 'analyze this'],
        'install' => ['component' => 'test-component'],
        'orchestrate' => ['action' => 'status'],
    ];

    foreach ($commands as $command => $args) {
        // Each command should work with just its primary argument
        $this->artisan($command, $args)
            ->assertExitCode(0);
    }
});

test('command names follow liberation naming convention', function () {
    $commands = array_keys(Artisan::all());

    foreach ($commands as $command) {
        // Skip Laravel commands
        if (str_contains($command, 'make:') || str_contains($command, 'migrate')) {
            continue;
        }

        // Liberation naming: verb or noun:verb, no complex hierarchies
        $parts = explode(':', $command);

        expect(count($parts))->toBeLessThanOrEqual(2,
            "Command {$command} has too many hierarchy levels. Keep it simple!");

        if (count($parts) === 2) {
            // component:action format
            expect($parts[0])->toMatch('/^[a-z]+$/',
                "Component name should be simple lowercase: {$parts[0]}");
            expect($parts[1])->toMatch('/^[a-z]+$/',
                "Action name should be simple lowercase: {$parts[1]}");
        } else {
            // single word command
            expect($command)->toMatch('/^[a-z]+$/',
                "Command name should be simple lowercase: {$command}");
        }
    }
});

test('commands complete without requiring follow-up commands', function () {
    // Install should complete the installation
    $this->artisan('install', ['component' => 'test-component'])
        ->doesntExpectOutput('Now run:')
        ->doesntExpectOutput('Next, execute:')
        ->doesntExpectOutput('To complete, run:');

    // Each command is self-contained
    $this->artisan('brain', ['query' => 'test'])
        ->doesntExpectOutput('Now run:')
        ->doesntExpectOutput('To continue:');
});

test('error messages provide solutions not just problems', function () {
    // When something fails, tell user how to fix it
    $this->artisan('install', ['component' => 'nonexistent-component-xyz'])
        ->expectsOutputToContain('not found')
        ->expectsOutputToContain('Try:'); // Should suggest alternatives
});

test('commands adapt to user type without configuration', function () {
    // Human user - no special config needed
    $_SERVER['CONDUIT_USER_AGENT'] = 'human';
    $this->artisan('brain', ['query' => 'test'])
        ->assertExitCode(0);

    // AI user - automatically adapts
    $_SERVER['CONDUIT_USER_AGENT'] = 'claude';
    $this->artisan('brain', ['query' => 'test'])
        ->assertExitCode(0);

    unset($_SERVER['CONDUIT_USER_AGENT']);
});
