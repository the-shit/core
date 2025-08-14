<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Universal activity logger for THE SHIT framework
 * Tracks ALL component activities, not just AI
 */
class ActivityLogger
{
    private string $logFile;

    public function __construct()
    {
        $this->logFile = storage_path('shit.jsonl');
        $this->ensureLogFileExists();
    }

    /**
     * Log any activity from any component
     */
    public function log(string $component, string $action, array $data = [], array $metadata = []): string
    {
        $activityId = Str::uuid()->toString();

        $entry = [
            'id' => $activityId,
            'timestamp' => now()->toIso8601String(),
            'component' => $component,  // e.g., 'ai', 'spotify', 'github'
            'action' => $action,         // e.g., 'ask', 'play', 'commit'
            'data' => $data,            // The actual data
            'metadata' => array_merge($metadata, [
                'user' => $_ENV['USER'] ?? 'unknown',
                'cwd' => getcwd(),
                'pid' => getmypid(),
            ]),
        ];

        File::append($this->logFile, json_encode($entry)."\n");

        // Also emit to component-specific logs if needed
        $this->emitToComponent($component, $entry);

        return $activityId;
    }

    /**
     * Query the activity log
     */
    public function query(array $filters = [], int $limit = 100): array
    {
        $results = [];
        $handle = fopen($this->logFile, 'r');

        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $entry = json_decode($line, true);
                if (! $entry) {
                    continue;
                }

                // Apply filters
                if ($this->matchesFilters($entry, $filters)) {
                    $results[] = $entry;
                    if (count($results) >= $limit) {
                        break;
                    }
                }
            }
            fclose($handle);
        }

        return array_reverse($results);
    }

    /**
     * Get activity statistics
     */
    public function stats(string $period = 'all'): array
    {
        $stats = [
            'total_activities' => 0,
            'by_component' => [],
            'by_action' => [],
            'by_day' => [],
            'most_active_component' => null,
            'most_common_action' => null,
        ];

        $handle = fopen($this->logFile, 'r');

        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $entry = json_decode($line, true);
                if (! $entry) {
                    continue;
                }

                // Filter by period if needed
                if (! $this->inPeriod($entry['timestamp'], $period)) {
                    continue;
                }

                $stats['total_activities']++;

                // Count by component
                $component = $entry['component'];
                $stats['by_component'][$component] = ($stats['by_component'][$component] ?? 0) + 1;

                // Count by action
                $action = $entry['action'];
                $stats['by_action'][$action] = ($stats['by_action'][$action] ?? 0) + 1;

                // Count by day
                $day = substr($entry['timestamp'], 0, 10);
                $stats['by_day'][$day] = ($stats['by_day'][$day] ?? 0) + 1;
            }
            fclose($handle);
        }

        // Determine most active
        if (! empty($stats['by_component'])) {
            $stats['most_active_component'] = array_keys($stats['by_component'], max($stats['by_component']))[0];
        }
        if (! empty($stats['by_action'])) {
            $stats['most_common_action'] = array_keys($stats['by_action'], max($stats['by_action']))[0];
        }

        return $stats;
    }

    /**
     * Stream activities in real-time (for monitoring)
     */
    public function tail(int $lines = 10): array
    {
        $output = [];
        $handle = fopen($this->logFile, 'r');

        if ($handle) {
            $buffer = [];
            while (($line = fgets($handle)) !== false) {
                $buffer[] = json_decode($line, true);
                if (count($buffer) > $lines) {
                    array_shift($buffer);
                }
            }
            fclose($handle);
            $output = $buffer;
        }

        return $output;
    }

    private function matchesFilters(array $entry, array $filters): bool
    {
        foreach ($filters as $key => $value) {
            if ($key === 'search') {
                // Search in all string values
                $found = false;
                array_walk_recursive($entry, function ($item) use ($value, &$found) {
                    if (is_string($item) && stripos($item, $value) !== false) {
                        $found = true;
                    }
                });
                if (! $found) {
                    return false;
                }
            } elseif ($key === 'since') {
                if ($entry['timestamp'] < $value) {
                    return false;
                }
            } elseif ($key === 'until') {
                if ($entry['timestamp'] > $value) {
                    return false;
                }
            } elseif (isset($entry[$key]) && $entry[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    private function inPeriod(string $timestamp, string $period): bool
    {
        return match ($period) {
            'today' => str_starts_with($timestamp, now()->toDateString()),
            'week' => now()->parse($timestamp)->isAfter(now()->subWeek()),
            'month' => now()->parse($timestamp)->isAfter(now()->subMonth()),
            default => true,
        };
    }

    private function emitToComponent(string $component, array $entry): void
    {
        // Components can have their own specific logs too
        $componentLog = base_path("💩-components/{$component}/events.jsonl");
        if (File::exists(dirname($componentLog))) {
            File::append($componentLog, json_encode($entry)."\n");
        }
    }

    private function ensureLogFileExists(): void
    {
        if (! File::exists($this->logFile)) {
            File::put($this->logFile, '');
        }
    }
}
