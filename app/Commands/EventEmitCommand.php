<?php

namespace App\Commands;

use App\Services\EventBusService;

class EventEmitCommand extends ConduitCommand
{
    protected $signature = 'event:emit {component : Component name} {event : Event name} {data? : JSON data}';
    
    protected $description = 'Emit an event to THE SHIT event bus';
    
    protected function executeCommand(): int
    {
        $component = $this->argument('component');
        $event = $this->argument('event');
        $data = $this->argument('data') ? json_decode($this->argument('data'), true) : [];
        
        // Store in database via activity log
        EventBusService::emit($component, $event, $data);
        
        $this->smartInfo("✅ Event emitted: {$component}.{$event}");
        
        // Also write to JSONL for backwards compatibility
        $eventData = [
            'component' => $component,
            'event' => "{$component}.{$event}",
            'data' => $data,
            'timestamp' => now()->toIso8601String()
        ];
        
        $queueFile = storage_path('events.jsonl');
        $dir = dirname($queueFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents(
            $queueFile,
            json_encode($eventData) . "\n",
            FILE_APPEND | LOCK_EX
        );
        
        return self::SUCCESS;
    }
}