<?php

namespace Tests\Unit;

use App\Providers\ComponentServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ComponentServiceProviderTest extends TestCase
{
    private string $testComponentsPath;

    private ComponentServiceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testComponentsPath = base_path('💩-components-test');
        $this->provider = new ComponentServiceProvider($this->app);

        // Create test components directory
        File::makeDirectory($this->testComponentsPath, 0755, false, true);
    }

    protected function tearDown(): void
    {
        // Clean up test components
        if (File::exists($this->testComponentsPath)) {
            File::deleteDirectory($this->testComponentsPath);
        }

        parent::tearDown();
    }

    private function createTestComponent(string $name, array $commands = [], bool $withExecutable = true): void
    {
        $componentPath = $this->testComponentsPath.'/'.$name;
        File::makeDirectory($componentPath, 0755, false, true);

        // Create manifest
        $manifest = [
            'name' => $name,
            'description' => 'Test component '.$name,
            'version' => '1.0.0',
            'commands' => $commands,
        ];

        File::put($componentPath.'/💩.json', json_encode($manifest, JSON_PRETTY_PRINT));

        // Create executable if requested
        if ($withExecutable) {
            $executable = $componentPath.'/'.$name;
            File::put($executable, "#!/usr/bin/env php\n<?php\necho 'Test component';");
            chmod($executable, 0755);
        }
    }
}

test('discovers components with manifest files', function () {
    // Create test components
    $this->createTestComponent('test-component', [
        'test-component:hello' => 'Say hello',
        'test-component:goodbye' => 'Say goodbye',
    ]);

    // Mock the base_path to use our test directory
    $originalPath = base_path();
    app()->bind('path.base', fn () => dirname($this->testComponentsPath));

    // Boot the provider
    $this->provider->boot();

    // Check if commands were registered
    $commands = Artisan::all();

    // Restore original path
    app()->bind('path.base', fn () => $originalPath);

    expect($commands)->toHaveKey('test-component:hello');
    expect($commands)->toHaveKey('test-component:goodbye');
});

test('skips components without manifest files', function () {
    // Create component without manifest
    $componentPath = $this->testComponentsPath.'/no-manifest';
    File::makeDirectory($componentPath, 0755, false, true);

    // Mock the base_path
    $originalPath = base_path();
    app()->bind('path.base', fn () => dirname($this->testComponentsPath));

    $commandsBefore = count(Artisan::all());
    $this->provider->boot();
    $commandsAfter = count(Artisan::all());

    app()->bind('path.base', fn () => $originalPath);

    expect($commandsAfter)->toBe($commandsBefore);
});

test('skips hidden directories starting with dot', function () {
    // Create hidden component
    $this->createTestComponent('.hidden-component', [
        'hidden:command' => 'Hidden command',
    ]);

    // Mock the base_path
    $originalPath = base_path();
    app()->bind('path.base', fn () => dirname($this->testComponentsPath));

    $this->provider->boot();

    $commands = Artisan::all();

    app()->bind('path.base', fn () => $originalPath);

    expect($commands)->not->toHaveKey('hidden:command');
});

test('handles malformed manifest files gracefully', function () {
    // Create component with invalid JSON
    $componentPath = $this->testComponentsPath.'/malformed';
    File::makeDirectory($componentPath, 0755, false, true);
    File::put($componentPath.'/💩.json', 'INVALID JSON {{{');

    // Mock the base_path
    $originalPath = base_path();
    app()->bind('path.base', fn () => dirname($this->testComponentsPath));

    // Should not throw exception
    expect(fn () => $this->provider->boot())->not->toThrow();

    app()->bind('path.base', fn () => $originalPath);
});

test('handles manifest without commands key', function () {
    // Create component without commands in manifest
    $componentPath = $this->testComponentsPath.'/no-commands';
    File::makeDirectory($componentPath, 0755, false, true);

    $manifest = [
        'name' => 'no-commands',
        'description' => 'Component without commands',
        'version' => '1.0.0',
    ];

    File::put($componentPath.'/💩.json', json_encode($manifest));

    // Mock the base_path
    $originalPath = base_path();
    app()->bind('path.base', fn () => dirname($this->testComponentsPath));

    // Should not throw exception
    expect(fn () => $this->provider->boot())->not->toThrow();

    app()->bind('path.base', fn () => $originalPath);
});

test('finds component executable in different locations', function () {
    // Test different executable naming conventions
    $testCases = [
        'component-with-named-binary' => 'component-with-named-binary',
        'component-with-default' => 'component',
        'component-with-application' => 'application',
    ];

    foreach ($testCases as $componentName => $binaryName) {
        $componentPath = $this->testComponentsPath.'/'.$componentName;
        File::makeDirectory($componentPath, 0755, false, true);

        // Create manifest
        $manifest = [
            'name' => $componentName,
            'commands' => [
                $componentName.':test' => 'Test command',
            ],
        ];
        File::put($componentPath.'/💩.json', json_encode($manifest));

        // Create executable with specific name
        $executable = $componentPath.'/'.$binaryName;
        File::put($executable, "#!/usr/bin/env php\n<?php\necho 'Test';");
        chmod($executable, 0755);
    }

    // Mock the base_path
    $originalPath = base_path();
    app()->bind('path.base', fn () => dirname($this->testComponentsPath));

    $this->provider->boot();

    $commands = Artisan::all();

    app()->bind('path.base', fn () => $originalPath);

    // All commands should be registered
    foreach ($testCases as $componentName => $binaryName) {
        expect($commands)->toHaveKey($componentName.':test');
    }
});

test('proxy command sets correct definition', function () {
    $this->createTestComponent('proxy-test', [
        'proxy-test:command' => 'Test proxy command',
    ]);

    // Mock the base_path
    $originalPath = base_path();
    app()->bind('path.base', fn () => dirname($this->testComponentsPath));

    $this->provider->boot();

    $command = Artisan::all()['proxy-test:command'];

    app()->bind('path.base', fn () => $originalPath);

    // Check command has correct description
    expect($command->getDescription())->toBe('Test proxy command');

    // Check command ignores validation errors
    $definition = $command->getDefinition();

    // Check it has the expected options
    expect($definition->hasOption('json'))->toBeTrue();
    expect($definition->hasOption('provider'))->toBeTrue();
    expect($definition->hasOption('model'))->toBeTrue();
    expect($definition->hasOption('stream'))->toBeTrue();
    expect($definition->hasOption('format'))->toBeTrue();

    // Check it has arguments array
    expect($definition->hasArgument('arguments'))->toBeTrue();
    $argument = $definition->getArgument('arguments');
    expect($argument->isArray())->toBeTrue();
});

test('handles missing components directory gracefully', function () {
    // Mock base_path to non-existent directory
    $originalPath = base_path();
    app()->bind('path.base', fn () => '/non/existent/path');

    // Should not throw exception
    expect(fn () => $this->provider->boot())->not->toThrow();

    app()->bind('path.base', fn () => $originalPath);
});

test('registers multiple components correctly', function () {
    // Create multiple test components
    $components = [
        'alpha' => [
            'alpha:one' => 'Alpha command one',
            'alpha:two' => 'Alpha command two',
        ],
        'beta' => [
            'beta:start' => 'Beta start command',
            'beta:stop' => 'Beta stop command',
        ],
        'gamma' => [
            'gamma:process' => 'Gamma process command',
        ],
    ];

    foreach ($components as $name => $commands) {
        $this->createTestComponent($name, $commands);
    }

    // Mock the base_path
    $originalPath = base_path();
    app()->bind('path.base', fn () => dirname($this->testComponentsPath));

    $this->provider->boot();

    $registeredCommands = Artisan::all();

    app()->bind('path.base', fn () => $originalPath);

    // Verify all commands were registered
    foreach ($components as $name => $commands) {
        foreach ($commands as $commandName => $description) {
            expect($registeredCommands)->toHaveKey($commandName);
            expect($registeredCommands[$commandName]->getDescription())->toBe($description);
        }
    }
});

test('component path resolution works correctly', function () {
    // Create a component with specific structure
    $componentName = 'path-test';
    $componentPath = $this->testComponentsPath.'/'.$componentName;
    File::makeDirectory($componentPath, 0755, false, true);

    // Create manifest
    $manifest = [
        'name' => $componentName,
        'commands' => [
            'path-test:resolve' => 'Test path resolution',
        ],
    ];
    File::put($componentPath.'/💩.json', json_encode($manifest));

    // Create executable
    $executable = $componentPath.'/'.$componentName;
    $testOutput = "Component path: {$componentPath}";
    File::put($executable, "#!/usr/bin/env php\n<?php\necho '{$testOutput}';");
    chmod($executable, 0755);

    // Mock the base_path
    $originalPath = base_path();
    app()->bind('path.base', fn () => dirname($this->testComponentsPath));

    $this->provider->boot();

    app()->bind('path.base', fn () => $originalPath);

    // The command should be registered
    expect(Artisan::all())->toHaveKey('path-test:resolve');
});
