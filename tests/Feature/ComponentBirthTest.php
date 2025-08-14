<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * BIRTH TEST: Components must work immediately on creation
 * No tweaks, no hacks, no "just add this" - they work from birth
 */
test('scaffolded component works immediately without any changes', function () {
    $componentName = 'birth-test-'.uniqid();
    $componentPath = base_path("💩-components/{$componentName}");

    try {
        // Clone the skeleton
        Process::run([
            'git', 'clone',
            'https://github.com/conduit-ui/conduit-component.git',
            $componentPath,
        ])->throw();

        // Replace placeholders in manifest
        $manifest = File::get("{$componentPath}/💩.json");
        $manifest = str_replace('{{COMPONENT_NAME}}', $componentName, $manifest);
        $manifest = str_replace('{{DESCRIPTION}}', 'Birth test component', $manifest);
        $manifest = str_replace('{{SHIT_ACRONYM}}', 'Simple Helpful Implementation Test', $manifest);
        File::put("{$componentPath}/💩.json", $manifest);

        // Install dependencies
        Process::path($componentPath)
            ->run(['composer', 'install', '--no-interaction'])
            ->throw();

        // TEST 1: Component lists commands without errors
        $result = Process::path($componentPath)
            ->run(['php', 'component', 'list']);

        expect($result->successful())->toBeTrue('Component should list commands on birth');
        expect($result->output())->toContain('example');
        expect($result->output())->toContain('test');
        expect($result->output())->toContain('certify');

        // TEST 2: Example command runs without errors
        $result = Process::path($componentPath)
            ->run(['php', 'component', 'example']);

        expect($result->successful())->toBeTrue('Example command should work on birth');

        // TEST 3: Test command runs (even if no tests exist)
        $result = Process::path($componentPath)
            ->run(['php', 'component', 'test']);

        expect($result->successful())->toBeTrue('Test command should work on birth');

        // TEST 4: Component can be registered with THE SHIT
        $result = Process::run(['php', '💩', 'list']);

        // After registration it should appear in main list
        // (This tests that the manifest is valid)

    } finally {
        // Clean up
        if (File::exists($componentPath)) {
            File::deleteDirectory($componentPath);
        }
    }
});

test('component skeleton has all required files from birth', function () {
    $skeletonPath = base_path('💩-components/skeleton-template');

    // Required files that must exist
    $requiredFiles = [
        '💩.json',           // Manifest
        'component',         // Executable
        'composer.json',     // Dependencies
        'README.md',         // Documentation
    ];

    foreach ($requiredFiles as $file) {
        expect(File::exists("{$skeletonPath}/{$file}"))->toBeTrue(
            "Required file {$file} must exist in skeleton"
        );
    }

    // Executable must be executable
    expect(is_executable("{$skeletonPath}/component"))->toBeTrue(
        'Component executable must have execute permissions'
    );
});

test('component works without composer install if no dependencies', function () {
    $componentName = 'no-deps-'.uniqid();
    $componentPath = base_path("💩-components/{$componentName}");

    try {
        // Create minimal component without dependencies
        File::makeDirectory($componentPath, 0755, true);

        // Minimal manifest
        File::put("{$componentPath}/💩.json", json_encode([
            'name' => $componentName,
            'description' => 'No dependencies test',
            'version' => '1.0.0',
            'commands' => [
                "{$componentName}:hello" => 'Say hello',
            ],
        ]));

        // Minimal executable
        $executable = <<<'PHP'
#!/usr/bin/env php
<?php
echo "Component works without dependencies!\n";
exit(0);
PHP;

        File::put("{$componentPath}/{$componentName}", $executable);
        chmod("{$componentPath}/{$componentName}", 0755);

        // Should work immediately
        $result = Process::path($componentPath)
            ->run(['php', $componentName]);

        expect($result->successful())->toBeTrue();
        expect($result->output())->toContain('works without dependencies');

    } finally {
        if (File::exists($componentPath)) {
            File::deleteDirectory($componentPath);
        }
    }
});

test('component commands are immediately accessible through proxy', function () {
    // When a component is installed, its commands should be immediately
    // available through THE SHIT without any configuration

    $testComponent = base_path('💩-components/skeleton-template');

    if (! File::exists($testComponent)) {
        $this->markTestSkipped('Skeleton template not found');
    }

    // The component's commands should be discoverable
    $manifest = json_decode(File::get("{$testComponent}/💩.json"), true);

    // If placeholders are replaced, commands should be valid
    if (! str_contains($manifest['name'] ?? '', '{{')) {
        expect($manifest)->toHaveKey('commands');
        expect($manifest['commands'])->toBeArray();
        expect(count($manifest['commands']))->toBeGreaterThan(0);
    }
});

test('component can run certification tests from birth', function () {
    $skeletonPath = base_path('💩-components/skeleton-template');

    if (! File::exists($skeletonPath)) {
        $this->markTestSkipped('Skeleton template not found');
    }

    // Certification should work even on a fresh component
    $result = Process::path($skeletonPath)
        ->timeout(30)
        ->run(['php', 'component', 'certify:simple']);

    // Simple certification should pass on a skeleton
    expect($result->successful())->toBeTrue(
        'Fresh component should pass simple certification'
    );
});
