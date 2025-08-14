<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Clean up orchestration directory before each test
    $orchestrationDir = storage_path('orchestration');
    if (File::exists($orchestrationDir)) {
        File::deleteDirectory($orchestrationDir);
    }
});

afterEach(function () {
    // Clean up after tests
    $orchestrationDir = storage_path('orchestration');
    if (File::exists($orchestrationDir)) {
        File::deleteDirectory($orchestrationDir);
    }
});

test('orchestrate command shows dashboard by default', function () {
    $this->artisan('orchestrate')
        ->expectsOutputToContain('THE SHIT Orchestration Dashboard')
        ->expectsOutputToContain('Active Work')
        ->expectsOutputToContain('Commands')
        ->assertSuccessful();
});

test('can assign work to an instance', function () {
    $this->artisan('orchestrate', [
        'action' => 'assign',
        '--instance' => 'test_instance_001',
        '--task' => 'Testing orchestration',
        '--files' => ['app/Test.php', 'app/Example.php'],
    ])
        ->expectsOutputToContain('Work assigned to instance: test_instance_001')
        ->expectsOutputToContain('Task: Testing orchestration')
        ->assertSuccessful();

    // Verify state was saved
    $state = json_decode(File::get(storage_path('orchestration/state.json')), true);
    expect($state['instances'])->toHaveKey('test_instance_001');
    expect($state['instances']['test_instance_001']['task'])->toBe('Testing orchestration');
    expect($state['instances']['test_instance_001']['status'])->toBe('active');
});

test('detects file conflicts between instances', function () {
    // Assign work to first instance
    $this->artisan('orchestrate', [
        'action' => 'assign',
        '--instance' => 'instance_001',
        '--task' => 'Task 1',
        '--files' => ['app/Shared.php'],
    ])->assertSuccessful();

    // Try to assign same file to second instance
    $this->artisan('orchestrate', [
        'action' => 'assign',
        '--instance' => 'instance_002',
        '--task' => 'Task 2',
        '--files' => ['app/Shared.php'],
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('File conflicts detected')
        ->expectsOutputToContain('app/Shared.php is locked by instance_001')
        ->assertFailed();
});

test('can release work from an instance', function () {
    // First assign work
    $this->artisan('orchestrate', [
        'action' => 'assign',
        '--instance' => 'release_test',
        '--task' => 'Task to release',
        '--files' => ['app/Release.php'],
    ])->assertSuccessful();

    // Then release it
    $this->artisan('orchestrate', [
        'action' => 'release',
        '--instance' => 'release_test',
    ])
        ->expectsOutputToContain('Released instance: release_test')
        ->assertSuccessful();

    // Verify state was updated
    $state = json_decode(File::get(storage_path('orchestration/state.json')), true);
    expect($state['instances']['release_test']['status'])->toBe('completed');
    expect($state['instances']['release_test'])->toHaveKey('completed_at');
});

test('shows orchestration status', function () {
    // Set up some test data
    $this->artisan('orchestrate', [
        'action' => 'assign',
        '--instance' => 'status_test',
        '--task' => 'Status test task',
        '--files' => ['app/Status.php'],
    ])->assertSuccessful();

    $this->artisan('orchestrate', ['action' => 'status'])
        ->expectsOutputToContain('Orchestration Status')
        ->expectsOutputToContain('Active Instances: 1')
        ->expectsOutputToContain('status_test')
        ->expectsOutputToContain('Status test task')
        ->assertSuccessful();
});

test('conflict detection command works', function () {
    // Create conflicting assignments
    $this->artisan('orchestrate', [
        'action' => 'assign',
        '--instance' => 'conflict_1',
        '--task' => 'Task 1',
        '--files' => ['app/Conflict.php'],
    ])->assertSuccessful();

    // Force assign to create conflict (using resolve flag)
    $this->artisan('orchestrate', [
        'action' => 'assign',
        '--instance' => 'conflict_2',
        '--task' => 'Task 2',
        '--files' => ['app/Conflict.php'],
        '--resolve' => true,
    ])->assertSuccessful();

    // Check conflicts
    $this->artisan('orchestrate', ['action' => 'conflicts'])
        ->expectsOutput('✅ No conflicts detected')
        ->assertSuccessful();
});

test('generates unique instance IDs', function () {
    $this->artisan('orchestrate', [
        'action' => 'assign',
        '--task' => 'Auto ID test',
        '--files' => ['app/AutoID.php'],
    ])
        ->expectsOutputToContain('Work assigned to instance: claude_')
        ->assertSuccessful();

    $state = json_decode(File::get(storage_path('orchestration/state.json')), true);
    $instanceIds = array_keys($state['instances']);

    expect($instanceIds)->toHaveCount(1);
    expect($instanceIds[0])->toStartWith('claude_');
});

test('supports non-interactive JSON mode', function () {
    // Assign work first
    $this->artisan('orchestrate', [
        'action' => 'assign',
        '--instance' => 'json_test',
        '--task' => 'JSON test',
        '--files' => ['app/Json.php'],
    ])->assertSuccessful();

    // Get status in JSON mode
    $this->artisan('orchestrate', [
        'action' => 'status',
        '--no-interaction' => true,
    ])->assertSuccessful();

    // The ConduitCommand should detect non-interactive mode and output JSON
    // We can't easily test the exact JSON output in this test framework,
    // but we can verify it runs without errors
});

test('tracks task history', function () {
    $this->artisan('orchestrate', [
        'action' => 'assign',
        '--instance' => 'history_test',
        '--task' => 'Historical task',
        '--files' => [],
    ])->assertSuccessful();

    $state = json_decode(File::get(storage_path('orchestration/state.json')), true);

    expect($state['tasks'])->toHaveCount(1);
    expect($state['tasks'][0]['description'])->toBe('Historical task');
    expect($state['tasks'][0]['instance'])->toBe('history_test');
    expect($state['tasks'][0])->toHaveKey('created_at');
});

test('file locks are properly created and cleaned', function () {
    $lockDir = storage_path('orchestration/locks');

    // Assign work with files
    $this->artisan('orchestrate', [
        'action' => 'assign',
        '--instance' => 'lock_test',
        '--task' => 'Lock test',
        '--files' => ['app/Lock1.php', 'app/Lock2.php'],
    ])->assertSuccessful();

    // Check locks exist
    expect(File::files($lockDir))->toHaveCount(2);

    // Release work
    $this->artisan('orchestrate', [
        'action' => 'release',
        '--instance' => 'lock_test',
    ])->assertSuccessful();

    // Check locks are removed
    expect(File::files($lockDir))->toHaveCount(0);
});
