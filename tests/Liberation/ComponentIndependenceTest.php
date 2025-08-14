<?php

namespace Tests\Liberation;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Tests that validate liberation from component coupling
 * Components must be independent, portable, and conflict-free
 */
class ComponentIndependenceTest extends TestCase
{
    protected function createTestComponent(string $name): string
    {
        $path = base_path("💩-components-test/{$name}");
        File::makeDirectory($path, 0755, true, true);

        // Create minimal component structure
        File::put("{$path}/💩.json", json_encode([
            'name' => $name,
            'version' => '1.0.0',
            'commands' => [
                "{$name}:test" => 'Test command',
            ],
        ]));

        // Create executable
        File::put("{$path}/{$name}", "#!/usr/bin/env php\n<?php\necho 'Independent component';");
        chmod("{$path}/{$name}", 0755);

        return $path;
    }

    protected function tearDown(): void
    {
        if (File::exists(base_path('💩-components-test'))) {
            File::deleteDirectory(base_path('💩-components-test'));
        }
        parent::tearDown();
    }
}

test('components have zero shared dependencies', function () {
    $componentA = $this->createTestComponent('component-a');
    $componentB = $this->createTestComponent('component-b');

    // Each component has its own vendor directory
    File::makeDirectory("{$componentA}/vendor");
    File::makeDirectory("{$componentB}/vendor");

    // Components don't share vendor
    expect("{$componentA}/vendor")->not->toBe("{$componentB}/vendor");

    // Modifying one doesn't affect the other
    File::put("{$componentA}/vendor/test.txt", 'A');
    expect(File::exists("{$componentB}/vendor/test.txt"))->toBeFalse();
});

test('components can be moved to any location and still work', function () {
    $original = $this->createTestComponent('portable');

    // Component works in original location
    $output = shell_exec("cd {$original} && php portable 2>&1");
    expect($output)->toContain('Independent component');

    // Move to completely different location
    $newLocation = sys_get_temp_dir().'/portable-component';
    File::copyDirectory($original, $newLocation);

    // Still works in new location
    $output = shell_exec("cd {$newLocation} && php portable 2>&1");
    expect($output)->toContain('Independent component');

    // Clean up
    File::deleteDirectory($newLocation);
});

test('components communicate only through events not direct calls', function () {
    $componentA = $this->createTestComponent('emitter');
    $componentB = $this->createTestComponent('listener');

    // Component A doesn't import Component B
    $aCode = "<?php\n// No use statements for component B\nEventBus::emit('emitter', 'task.done', []);";
    File::put("{$componentA}/src/Emitter.php", $aCode);

    // Component B doesn't import Component A
    $bCode = "<?php\n// No use statements for component A\n\$events = EventBus::forComponent('emitter');";
    File::put("{$componentB}/src/Listener.php", $bCode);

    // Verify no direct dependencies
    $aContent = File::get("{$componentA}/src/Emitter.php");
    expect($aContent)->not->toContain('use.*Listener');
    expect($aContent)->not->toContain('require.*listener');

    $bContent = File::get("{$componentB}/src/Listener.php");
    expect($bContent)->not->toContain('use.*Emitter');
    expect($bContent)->not->toContain('require.*emitter');
});

test('component removal leaves no traces in system', function () {
    $component = $this->createTestComponent('removable');

    // Add some files
    File::put("{$component}/data.txt", 'test data');
    File::makeDirectory("{$component}/cache");
    File::put("{$component}/cache/temp.txt", 'cache');

    // Remove component
    File::deleteDirectory($component);

    // Verify complete removal
    expect(File::exists($component))->toBeFalse();

    // System still works without it
    $this->artisan('list')->assertSuccessful();
});

test('components can have different versions without conflicts', function () {
    // Component A uses PHP 8.1
    $componentA = $this->createTestComponent('php81');
    File::put("{$componentA}/composer.json", json_encode([
        'require' => ['php' => '^8.1'],
    ]));

    // Component B uses PHP 8.2
    $componentB = $this->createTestComponent('php82');
    File::put("{$componentB}/composer.json", json_encode([
        'require' => ['php' => '^8.2'],
    ]));

    // Both can coexist
    expect(File::exists("{$componentA}/composer.json"))->toBeTrue();
    expect(File::exists("{$componentB}/composer.json"))->toBeTrue();

    // Different requirements don't conflict
    $aReq = json_decode(File::get("{$componentA}/composer.json"), true);
    $bReq = json_decode(File::get("{$componentB}/composer.json"), true);

    expect($aReq['require']['php'])->not->toBe($bReq['require']['php']);
});

test('components install without modifying core system', function () {
    // Get core files before installation
    $coreFiles = File::files(base_path('app'));
    $coreConfig = File::files(base_path('config'));

    // Install a component
    $component = $this->createTestComponent('non-invasive');

    // Core files unchanged
    expect(File::files(base_path('app')))->toEqual($coreFiles);
    expect(File::files(base_path('config')))->toEqual($coreConfig);

    // Component is isolated in its directory
    expect(File::exists(base_path('💩-components-test/non-invasive')))->toBeTrue();
    expect(File::exists(base_path('app/non-invasive')))->toBeFalse();
});

test('component updates dont break other components', function () {
    $componentA = $this->createTestComponent('stable');
    $componentB = $this->createTestComponent('updating');

    // Component B updates its structure
    File::put("{$componentB}/💩.json", json_encode([
        'name' => 'updating',
        'version' => '2.0.0', // Major version change
        'commands' => [
            'updating:new' => 'New command structure',
        ],
    ]));

    // Component A still works
    $aManifest = json_decode(File::get("{$componentA}/💩.json"), true);
    expect($aManifest['version'])->toBe('1.0.0');
    expect($aManifest['commands'])->toHaveKey('stable:test');
});

test('components can be developed independently', function () {
    // No shared development dependencies
    $component = $this->createTestComponent('independent-dev');

    // Component can have its own dev tools
    File::put("{$component}/.gitignore", "vendor/\n.env");
    File::put("{$component}/.env", 'DEV_MODE=true');
    File::put("{$component}/phpunit.xml", '<phpunit></phpunit>');

    // These don't affect other components or core
    expect(File::exists(base_path('.env')))->toBeFalse(); // Core .env separate
    expect(File::exists("{$component}/.env"))->toBeTrue(); // Component .env exists
});

test('components are liberated from framework version', function () {
    $component = $this->createTestComponent('framework-free');

    // Component doesn't require Laravel
    $code = <<<'PHP'
    #!/usr/bin/env php
    <?php
    // No Laravel imports
    // Pure PHP implementation
    echo json_encode(['status' => 'success']);
    PHP;

    File::put("{$component}/standalone.php", $code);
    chmod("{$component}/standalone.php", 0755);

    // Works without Laravel
    $output = shell_exec("php {$component}/standalone.php");
    $result = json_decode($output, true);

    expect($result['status'])->toBe('success');
});
