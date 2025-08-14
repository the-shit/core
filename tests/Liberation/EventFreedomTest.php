<?php

namespace Tests\Liberation;

use App\Services\EventBusService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Tests that validate liberation through event-driven architecture
 * Events must flow freely without coupling or configuration
 */
class EventFreedomTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clean event file for each test
        if (File::exists(storage_path('events.jsonl'))) {
            File::delete(storage_path('events.jsonl'));
        }
    }
}

test('events flow without components knowing about each other', function () {
    // Component A emits without knowing who listens
    EventBusService::emit('component-a', 'task.completed', [
        'task_id' => 123,
        'result' => 'success',
    ]);

    // Component B can read without knowing who emitted
    $events = EventBusService::recent();
    expect($events->first()['event'])->toBe('component-a.task.completed');

    // Component C can also read the same event
    $events = EventBusService::byEvent('task.completed');
    expect($events)->toHaveCount(1);

    // No registration required, no coupling needed
});

test('components can emit events without configuration', function () {
    // No setup required
    EventBusService::emit('test', 'started', []);

    // Event is immediately available
    $events = EventBusService::recent(1);
    expect($events->first()['event'])->toBe('test.started');

    // No event schema registration
    // No event type definition
    // No configuration files
});

test('event replay enables time travel debugging', function () {
    // Emit series of events
    EventBusService::emit('debug', 'step1', ['data' => 'first']);
    EventBusService::emit('debug', 'step2', ['data' => 'second']);
    EventBusService::emit('debug', 'error', ['data' => 'failed']);
    EventBusService::emit('debug', 'step3', ['data' => 'third']);

    // Can replay events to understand what happened
    $debugEvents = EventBusService::forComponent('debug');

    expect($debugEvents)->toHaveCount(4);
    expect($debugEvents->pluck('event')->toArray())->toBe([
        'debug.step3',
        'debug.error',
        'debug.step2',
        'debug.step1',
    ]);

    // Can find where things went wrong
    $errorEvents = EventBusService::byEvent('error');
    expect($errorEvents->first()['data']['data'])->toBe('failed');
});

test('events persist without database configuration', function () {
    // No database setup needed
    EventBusService::emit('persist', 'test', ['value' => 42]);

    // Events stored in simple JSONL file
    expect(File::exists(storage_path('events.jsonl')))->toBeTrue();

    // Can be read immediately
    $content = File::get(storage_path('events.jsonl'));
    expect($content)->toContain('persist.test');
    expect($content)->toContain('"value":42');
});

test('event filtering requires no complex queries', function () {
    // Emit various events
    EventBusService::emit('app', 'user.login', ['user' => 1]);
    EventBusService::emit('app', 'user.logout', ['user' => 1]);
    EventBusService::emit('system', 'cache.cleared', []);
    EventBusService::emit('app', 'user.login', ['user' => 2]);

    // Simple filtering by component
    $appEvents = EventBusService::forComponent('app');
    expect($appEvents)->toHaveCount(3);

    // Simple filtering by event type
    $loginEvents = EventBusService::byEvent('login');
    expect($loginEvents)->toHaveCount(2);

    // No SQL, no query builders, no ORMs
});

test('events enable component discovery without registration', function () {
    // Components announce themselves through events
    EventBusService::emit('new-component', 'initialized', [
        'capabilities' => ['search', 'analyze'],
    ]);

    // Other components discover capabilities
    $events = EventBusService::byEvent('initialized');
    $newComponent = $events->first();

    expect($newComponent['component'])->toBe('new-component');
    expect($newComponent['data']['capabilities'])->toContain('search');

    // No service registry needed
    // No dependency injection configuration
});

test('event ordering preserves causality without timestamps', function () {
    // Events maintain order even without precise timestamps
    for ($i = 1; $i <= 5; $i++) {
        EventBusService::emit('sequence', "step{$i}", ['order' => $i]);
    }

    // Recent events come in correct order
    $events = EventBusService::recent(5);

    // Most recent first
    expect($events->first()['data']['order'])->toBe(5);
    expect($events->last()['data']['order'])->toBe(1);

    // Causality preserved without complex timestamp management
});

test('events support debugging without special tools', function () {
    // Regular events are debuggable
    EventBusService::emit('debug', 'process.started', [
        'memory' => memory_get_usage(),
        'time' => microtime(true),
    ]);

    // Can inspect with basic tools
    $events = EventBusService::search('memory');
    expect($events)->toHaveCount(1);

    // Can trace with simple grep
    $eventFile = storage_path('events.jsonl');
    $debugEvents = shell_exec("grep debug {$eventFile} 2>/dev/null") ?? '';
    expect($debugEvents)->toContain('process.started');
});

test('event bus handles high volume without configuration', function () {
    // Emit many events rapidly
    for ($i = 0; $i < 1000; $i++) {
        EventBusService::emit('load', 'test', ['index' => $i]);
    }

    // All events captured
    $lines = count(file(storage_path('events.jsonl')));
    expect($lines)->toBe(1000);

    // Still fast to query
    $recent = EventBusService::recent(10);
    expect($recent)->toHaveCount(10);

    // No configuration needed for performance
});

test('events liberate from synchronous coupling', function () {
    // Component A completes its work
    EventBusService::emit('worker-a', 'job.done', ['job_id' => 'abc']);

    // Component A doesn't wait for anyone
    // Component B processes when ready (could be later)
    $pendingJobs = EventBusService::byEvent('job.done');

    // Component B handles at its own pace
    foreach ($pendingJobs as $job) {
        // Process asynchronously
        expect($job['data'])->toHaveKey('job_id');
    }

    // No blocking, no waiting, no timeouts
});
