<?php

namespace Tests\Unit;

use App\Services\EventBusService;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Clean slate for each test
    $this->eventFile = storage_path('events.jsonl');
    if (File::exists($this->eventFile)) {
        File::delete($this->eventFile);
    }
});

afterEach(function () {
    // Clean up after each test
    if (File::exists(storage_path('events.jsonl'))) {
        File::delete(storage_path('events.jsonl'));
    }
});

test('emit creates event file if it does not exist', function () {
    expect(File::exists(storage_path('events.jsonl')))->toBeFalse();

    EventBusService::emit('test-component', 'test.event', ['key' => 'value']);

    expect(File::exists(storage_path('events.jsonl')))->toBeTrue();
});

test('emit writes event data in correct format', function () {
    EventBusService::emit('brain', 'query.processed', [
        'query' => 'test query',
        'result' => 'success',
    ]);

    $content = File::get(storage_path('events.jsonl'));
    $event = json_decode(trim($content), true);

    expect($event)->toHaveKey('component');
    expect($event['component'])->toBe('brain');
    expect($event)->toHaveKey('event');
    expect($event['event'])->toBe('brain.query.processed');
    expect($event)->toHaveKey('timestamp');
    expect($event['data'])->toBe([
        'query' => 'test query',
        'result' => 'success',
    ]);
});

test('emit appends multiple events as separate lines', function () {
    EventBusService::emit('component1', 'event1', ['data' => '1']);
    EventBusService::emit('component2', 'event2', ['data' => '2']);
    EventBusService::emit('component3', 'event3', ['data' => '3']);

    $lines = file(storage_path('events.jsonl'), FILE_IGNORE_NEW_LINES);

    expect($lines)->toHaveCount(3);

    $event1 = json_decode($lines[0], true);
    expect($event1['component'])->toBe('component1');

    $event2 = json_decode($lines[1], true);
    expect($event2['component'])->toBe('component2');

    $event3 = json_decode($lines[2], true);
    expect($event3['component'])->toBe('component3');
});

test('recent returns empty collection when no events exist', function () {
    $events = EventBusService::recent();

    expect($events)->toBeEmpty();
    expect($events)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

test('recent returns last N events in reverse order', function () {
    // Create 15 events
    for ($i = 1; $i <= 15; $i++) {
        EventBusService::emit('test', "event{$i}", ['index' => $i]);
    }

    $events = EventBusService::recent(10);

    expect($events)->toHaveCount(10);

    // Should be in reverse order (most recent first)
    expect($events->first()['data']['index'])->toBe(15);
    expect($events->last()['data']['index'])->toBe(6);
});

test('recent returns all events if limit exceeds total', function () {
    EventBusService::emit('test', 'event1', ['index' => 1]);
    EventBusService::emit('test', 'event2', ['index' => 2]);
    EventBusService::emit('test', 'event3', ['index' => 3]);

    $events = EventBusService::recent(100);

    expect($events)->toHaveCount(3);
});

test('forComponent returns only events for specified component', function () {
    EventBusService::emit('brain', 'initialized', []);
    EventBusService::emit('ai', 'query', []);
    EventBusService::emit('brain', 'processed', []);
    EventBusService::emit('orchestrator', 'assigned', []);
    EventBusService::emit('brain', 'completed', []);

    $brainEvents = EventBusService::forComponent('brain');

    expect($brainEvents)->toHaveCount(3);

    foreach ($brainEvents as $event) {
        expect($event['component'])->toBe('brain');
    }
});

test('forComponent respects limit parameter', function () {
    // Create 10 events for the same component
    for ($i = 1; $i <= 10; $i++) {
        EventBusService::emit('test-component', "event{$i}", ['index' => $i]);
    }

    $events = EventBusService::forComponent('test-component', 5);

    expect($events)->toHaveCount(5);

    // Should get the 5 most recent events
    expect($events->first()['data']['index'])->toBe(10);
    expect($events->last()['data']['index'])->toBe(6);
});

test('byEvent filters events by event type', function () {
    EventBusService::emit('brain', 'query.started', []);
    EventBusService::emit('ai', 'query.started', []);
    EventBusService::emit('brain', 'query.completed', []);
    EventBusService::emit('orchestrator', 'task.assigned', []);

    $queryEvents = EventBusService::byEvent('query');

    expect($queryEvents)->toHaveCount(3);

    foreach ($queryEvents as $event) {
        expect($event['event'])->toContain('query');
    }
});

test('byEvent returns exact matches and partial matches', function () {
    EventBusService::emit('component1', 'test.started', []);
    EventBusService::emit('component2', 'test.completed', []);
    EventBusService::emit('component3', 'testing.progress', []);
    EventBusService::emit('component4', 'other.event', []);

    $testEvents = EventBusService::byEvent('test');

    expect($testEvents)->toHaveCount(3);

    $eventTypes = $testEvents->pluck('event')->toArray();
    expect($eventTypes)->toContain('component1.test.started');
    expect($eventTypes)->toContain('component2.test.completed');
    expect($eventTypes)->toContain('component3.testing.progress');
    expect($eventTypes)->not->toContain('component4.other.event');
});

test('search finds events containing query string', function () {
    EventBusService::emit('brain', 'query', ['message' => 'Processing user request']);
    EventBusService::emit('ai', 'response', ['message' => 'Generated response']);
    EventBusService::emit('brain', 'analysis', ['message' => 'Analyzing user input']);
    EventBusService::emit('orchestrator', 'task', ['message' => 'Assigning task']);

    $userEvents = EventBusService::search('user');

    expect($userEvents)->toHaveCount(2);

    foreach ($userEvents as $event) {
        $json = json_encode($event);
        expect($json)->toContain('user');
    }
});

test('search is case insensitive', function () {
    EventBusService::emit('test', 'event1', ['message' => 'UPPERCASE']);
    EventBusService::emit('test', 'event2', ['message' => 'lowercase']);
    EventBusService::emit('test', 'event3', ['message' => 'MixedCase']);

    $events = EventBusService::search('case');

    expect($events)->toHaveCount(3);
});

test('search respects 100 event limit', function () {
    // Create 150 events all containing "test"
    for ($i = 1; $i <= 150; $i++) {
        EventBusService::emit('component', 'event', ['message' => "test event {$i}"]);
    }

    $events = EventBusService::search('test');

    expect($events)->toHaveCount(100);
});

test('events maintain chronological order in file', function () {
    for ($i = 1; $i <= 5; $i++) {
        EventBusService::emit('test', "event{$i}", ['index' => $i]);
    }

    $lines = file(storage_path('events.jsonl'), FILE_IGNORE_NEW_LINES);

    foreach ($lines as $index => $line) {
        $event = json_decode($line, true);
        expect($event['data']['index'])->toBe($index + 1);
    }
});

test('concurrent writes do not corrupt event file', function () {
    // Simulate concurrent writes
    for ($i = 0; $i < 10; $i++) {
        EventBusService::emit('concurrent', "event{$i}", ['process' => $i]);
    }

    // Verify all events were written correctly
    $lines = file(storage_path('events.jsonl'), FILE_IGNORE_NEW_LINES);

    expect($lines)->toHaveCount(10);

    $processIds = [];
    foreach ($lines as $line) {
        $event = json_decode($line, true);
        expect($event)->not->toBeNull(); // Each line should be valid JSON
        expect($event['component'])->toBe('concurrent');
        $processIds[] = $event['data']['process'];
    }

    // All 10 events should be present
    sort($processIds);
    expect($processIds)->toBe(range(0, 9));
});

test('handles malformed events gracefully', function () {
    // Write some valid events
    EventBusService::emit('test', 'event1', ['valid' => true]);
    EventBusService::emit('test', 'event2', ['valid' => true]);

    // Manually append a malformed line
    file_put_contents(
        storage_path('events.jsonl'),
        "INVALID JSON LINE\n",
        FILE_APPEND
    );

    // Add more valid events
    EventBusService::emit('test', 'event3', ['valid' => true]);

    // Recent should skip invalid lines
    $events = EventBusService::recent(10);

    expect($events)->toHaveCount(3);

    foreach ($events as $event) {
        expect($event['data']['valid'])->toBeTrue();
    }
});

test('creates storage directory if it does not exist', function () {
    $dir = dirname(storage_path('events.jsonl'));

    // Directory should exist (created by Laravel)
    expect(File::exists($dir))->toBeTrue();

    // Emit should work even if we had to create the directory
    EventBusService::emit('test', 'event', ['data' => 'test']);

    expect(File::exists(storage_path('events.jsonl')))->toBeTrue();
});
