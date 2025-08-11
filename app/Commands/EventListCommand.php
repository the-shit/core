<?php

namespace App\Commands;

use App\Services\EventBusService;
use function Laravel\Prompts\table;

class EventListCommand extends ConduitCommand
{
    protected $signature = 'event:list {--tail=10 : Number of recent events to show} {--component= : Filter by component} {--event= : Filter by event type}';
    
    protected $description = 'View events from components';
    
    protected function executeCommand(): int
    {
        $limit = (int) $this->option('tail');
        $component = $this->option('component');
        $eventType = $this->option('event');
        
        // Get events based on filters
        if ($component) {
            $events = EventBusService::forComponent($component, $limit);
        } elseif ($eventType) {
            $events = EventBusService::byEvent($eventType, $limit);
        } else {
            $events = EventBusService::recent($limit);
        }
        
        if ($events->isEmpty()) {
            $this->smartInfo('📭 No events found');
            return self::SUCCESS;
        }
        
        // Display as table
        $rows = $events->map(fn($e) => [
            is_array($e) ? ($e['timestamp'] ?? 'unknown') : $e->created_at->format('Y-m-d H:i:s'),
            is_array($e) ? ($e['component'] ?? 'unknown') : ($e->causer_type ?? 'system'),
            is_array($e) ? ($e['event'] ?? 'unknown') : ($e->event ?? $e->description),
            is_array($e) ? json_encode($e['data'] ?? [], JSON_UNESCAPED_SLASHES) : json_encode($e->properties ?? [], JSON_UNESCAPED_SLASHES)
        ])->toArray();
        
        table(
            ['Time', 'Component', 'Event', 'Data'],
            $rows
        );
        
        $this->smartInfo("📊 Showing {$events->count()} events");
        
        return self::SUCCESS;
    }
    
    private function followEvents(string $file): int
    {
        $this->smartInfo('📡 Watching for events... (Ctrl+C to stop)');
        
        $lastSize = filesize($file);
        
        while (true) {
            clearstatcache(true, $file);
            $currentSize = filesize($file);
            
            if ($currentSize > $lastSize) {
                // New content added
                $fp = fopen($file, 'r');
                fseek($fp, $lastSize);
                
                while ($line = fgets($fp)) {
                    $event = json_decode(trim($line), true);
                    if ($event) {
                        $this->line(sprintf(
                            "[%s] %s.%s: %s",
                            $event['timestamp'] ?? 'now',
                            $event['component'] ?? '?',
                            $event['event'] ?? '?',
                            json_encode($event['data'] ?? [])
                        ));
                    }
                }
                
                fclose($fp);
                $lastSize = $currentSize;
            }
            
            sleep(1);
        }
        
        return self::SUCCESS;
    }
}