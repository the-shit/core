<?php

namespace App\Services;

use Spatie\Activitylog\Models\Activity;

class EventBusService
{
    /**
     * Emit an event from a component
     */
    public static function emit(string $component, string $event, array $data = []): Activity
    {
        return activity()
            ->causedByAnonymous()  // No auth system, so anonymous
            ->withProperties(array_merge($data, ['component' => $component]))
            ->event($event)
            ->log("{$component}.{$event}");
    }
    
    /**
     * Get recent events
     */
    public static function recent(int $limit = 10): \Illuminate\Support\Collection
    {
        return Activity::latest()
            ->limit($limit)
            ->get()
            ->map(fn($activity) => [
                'id' => $activity->id,
                'timestamp' => $activity->created_at->toIso8601String(),
                'component' => $activity->causer_type ?? 'unknown',
                'event' => $activity->event,
                'description' => $activity->description,
                'data' => $activity->properties->toArray(),
            ]);
    }
    
    /**
     * Get events for a specific component
     */
    public static function forComponent(string $component, int $limit = 100): \Illuminate\Support\Collection
    {
        return Activity::where('causer_type', $component)
            ->latest()
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get events by type
     */
    public static function byEvent(string $event, int $limit = 100): \Illuminate\Support\Collection
    {
        return Activity::where('event', $event)
            ->latest()
            ->limit($limit)
            ->get();
    }
    
    /**
     * Search events
     */
    public static function search(string $query): \Illuminate\Support\Collection
    {
        return Activity::where('description', 'like', "%{$query}%")
            ->orWhere('event', 'like', "%{$query}%")
            ->orWhere('properties', 'like', "%{$query}%")
            ->latest()
            ->limit(100)
            ->get();
    }
}