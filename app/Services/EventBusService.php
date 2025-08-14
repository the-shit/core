<?php

namespace App\Services;

use Illuminate\Support\Collection;

class EventBusService
{
    private static function getEventFile(): string
    {
        return storage_path('events.jsonl');
    }

    /**
     * Emit an event from a component
     */
    public static function emit(string $component, string $event, array $data = []): void
    {
        $eventData = [
            'component' => $component,
            'event' => "{$component}.{$event}",
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ];

        $file = self::getEventFile();
        $dir = dirname($file);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $file,
            json_encode($eventData)."\n",
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * Get recent events
     */
    public static function recent(int $limit = 10): Collection
    {
        $file = self::getEventFile();
        if (! file_exists($file)) {
            return collect();
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $events = collect();

        // Get last $limit lines
        $recentLines = array_slice($lines, -$limit);

        foreach (array_reverse($recentLines) as $line) {
            $event = json_decode($line, true);
            if ($event) {
                $events->push($event);
            }
        }

        return $events;
    }

    /**
     * Get events for a specific component
     */
    public static function forComponent(string $component, int $limit = 100): Collection
    {
        $file = self::getEventFile();
        if (! file_exists($file)) {
            return collect();
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $events = collect();

        foreach (array_reverse($lines) as $line) {
            $event = json_decode($line, true);
            if ($event && $event['component'] === $component) {
                $events->push($event);
                if ($events->count() >= $limit) {
                    break;
                }
            }
        }

        return $events;
    }

    /**
     * Get events by type
     */
    public static function byEvent(string $event, int $limit = 100): Collection
    {
        $file = self::getEventFile();
        if (! file_exists($file)) {
            return collect();
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $events = collect();

        foreach (array_reverse($lines) as $line) {
            $eventData = json_decode($line, true);
            if ($eventData && str_contains($eventData['event'], $event)) {
                $events->push($eventData);
                if ($events->count() >= $limit) {
                    break;
                }
            }
        }

        return $events;
    }

    /**
     * Search events
     */
    public static function search(string $query): Collection
    {
        $file = self::getEventFile();
        if (! file_exists($file)) {
            return collect();
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $events = collect();

        foreach (array_reverse($lines) as $line) {
            if (stripos($line, $query) !== false) {
                $event = json_decode($line, true);
                if ($event) {
                    $events->push($event);
                    if ($events->count() >= 100) {
                        break;
                    }
                }
            }
        }

        return $events;
    }
}
