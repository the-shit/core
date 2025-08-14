# 🔌 THE SHIT Integration Guide

## Overview

This guide covers how components integrate with THE SHIT core and with each other. Integration is designed to be simple, flexible, and conflict-free.

## Integration Levels

### Level 1: Standalone Operation
Components work independently:
```bash
# Direct execution
cd 💩-components/github
php component pr:create "Fix bug" --body="Fixes #123"
```

### Level 2: CLI Integration
Components integrate with main CLI:
```bash
# Proxied through main executable
php 💩 github:pr:create "Fix bug" --body="Fixes #123"
```

### Level 3: Inter-Component Communication
Components work together:
```php
// Deploy component uses GitHub component
EventBusService::emit('deploy.completed', [...]);
// GitHub component creates release automatically
```

### Level 4: Ecosystem Integration
Full ecosystem participation:
- Service discovery
- Event mesh
- Shared storage
- Orchestration

## Installation Integration

### 1. Component Discovery
```bash
# Find components on GitHub
php 💩 component:search cache

# Results show available components:
# - jordanpartridge/shit-cache (Official cache component)
# - community/awesome-cache (Community cache)
```

### 2. Installation Process
```bash
# Install component
php 💩 install cache

# What happens:
1. Clones from GitHub to 💩-components/cache
2. Runs composer install in component directory
3. Registers component in manifest
4. Proxies commands to main CLI
5. Runs post-install hooks
```

### 3. Post-Install Setup
```php
// Component's post-install hook
class SetupCommand extends Command
{
    public function handle()
    {
        // Create config file
        $this->publishConfig();
        
        // Set up database tables
        $this->runMigrations();
        
        // Register with service registry
        ServiceRegistry::register('cache', CacheService::class);
        
        // Subscribe to events
        EventBusService::subscribe('cache.clear', [$this, 'clearCache']);
    }
}
```

## Command Integration

### Command Proxying
Main CLI automatically proxies to components:

```php
// ComponentServiceProvider.php
private function registerProxyCommand($componentPath, $commandName, $description)
{
    Artisan::command($commandName, function () use ($componentPath, $commandName) {
        // Get raw argv for perfect argument passing
        global $argv;
        
        // Build command array
        $command = [PHP_BINARY, $componentPath . '/component', ...];
        
        // Execute with TTY support
        return Process::tty()->run($command)->exitCode();
    })
    ->describe($description)
    ->ignoreValidationErrors(); // Pass all args through
}
```

### Command Naming Convention
```
component:resource:action

Examples:
github:pr:create       # Create a PR
github:pr:list        # List PRs
github:issue:close    # Close an issue
deploy:rollback       # Rollback deployment
cache:clear           # Clear cache
```

### Command Discovery
Commands are auto-discovered from manifest:
```json
{
    "commands": {
        "github:pr": "Manage pull requests",
        "github:pr:create": "Create a new PR",
        "github:pr:merge": "Merge a PR"
    }
}
```

## Event Integration

### Event Bus Architecture
```php
// Central event bus service
class EventBusService
{
    private static array $listeners = [];
    private static string $eventLog = 'storage/events.jsonl';
    
    public static function emit(string $event, array $data = []): void
    {
        // Log event
        $eventData = [
            'event' => $event,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
            'component' => self::detectComponent()
        ];
        
        File::append(self::$eventLog, json_encode($eventData) . "\n");
        
        // Notify listeners
        foreach (self::$listeners[$event] ?? [] as $listener) {
            $listener($data);
        }
    }
    
    public static function on(string $event, callable $listener): void
    {
        self::$listeners[$event][] = $listener;
    }
}
```

### Event Patterns

#### 1. Request-Response Pattern
```php
// Component A requests service
EventBusService::emit('cache.get', [
    'key' => 'user.123',
    'callback' => function($value) {
        // Handle cached value
    }
]);

// Cache component responds
EventBusService::on('cache.get', function($data) {
    $value = $this->cache->get($data['key']);
    $data['callback']($value);
});
```

#### 2. Publish-Subscribe Pattern
```php
// GitHub component publishes
EventBusService::emit('github.pr.merged', [
    'pr_number' => 123,
    'branch' => 'feature-x'
]);

// Multiple components subscribe
// Deploy component
EventBusService::on('github.pr.merged', function($data) {
    if ($data['branch'] === 'main') {
        $this->deployToStaging();
    }
});

// Notification component
EventBusService::on('github.pr.merged', function($data) {
    $this->notifyTeam("PR #{$data['pr_number']} merged!");
});
```

#### 3. Pipeline Pattern
```php
// Chain events through pipeline
EventBusService::emit('pipeline.start', [
    'pipeline' => 'ci',
    'stages' => ['test', 'build', 'deploy']
]);

EventBusService::on('stage.test.complete', function($data) {
    EventBusService::emit('stage.build.start', $data);
});

EventBusService::on('stage.build.complete', function($data) {
    EventBusService::emit('stage.deploy.start', $data);
});
```

## Service Integration

### Service Registry
Components register services for others to use:

```php
// Cache component registers service
class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $service = new CacheService(config('cache'));
        ServiceRegistry::register('cache', $service);
    }
}

// Other components use the service
class DeployCommand extends Command
{
    public function handle()
    {
        $cache = ServiceRegistry::get('cache');
        $cache->remember('deploy.config', 3600, function() {
            return $this->loadDeployConfig();
        });
    }
}
```

### Service Discovery
```php
// Check if service is available
if (ServiceRegistry::has('cache')) {
    $cache = ServiceRegistry::get('cache');
    $data = $cache->get('key');
} else {
    // Fallback to direct storage
    $data = Storage::get('key');
}
```

### Service Contracts
Define interfaces for services:
```php
// contracts/CacheInterface.php
interface CacheInterface
{
    public function get(string $key): mixed;
    public function put(string $key, mixed $value, int $ttl = 3600): void;
    public function forget(string $key): void;
    public function flush(): void;
}

// Implement in component
class RedisCache implements CacheInterface
{
    // Implementation...
}
```

## Storage Integration

### Shared Storage Areas
```
storage/
├── components/          # Component-specific storage
│   ├── github/         # GitHub component data
│   └── cache/          # Cache component data
├── shared/             # Shared between components
│   ├── registry.json   # Service registry
│   └── config.json     # Shared configuration
└── events.jsonl        # Event log
```

### Storage Access Patterns
```php
// Component-specific storage
Storage::disk('component')->put('github/pulls.json', $data);

// Shared storage
Storage::disk('shared')->put('registry.json', $registry);

// Event storage
File::append(storage_path('events.jsonl'), json_encode($event) . "\n");
```

## Configuration Integration

### Configuration Hierarchy
1. Component defaults
2. User configuration
3. Environment variables
4. Runtime overrides

```php
// Load configuration in order
$config = array_merge(
    include __DIR__ . '/config/defaults.php',    // 1. Defaults
    include base_path('config/github.php'),      // 2. User config
    ['token' => env('GITHUB_TOKEN')],            // 3. Env vars
    ['token' => $this->option('token')]          // 4. Runtime
);
```

### Shared Configuration
```php
// Central configuration service
class ConfigService
{
    public static function get(string $key, mixed $default = null): mixed
    {
        // Check component config first
        $componentConfig = "components.{$key}";
        if (config()->has($componentConfig)) {
            return config($componentConfig);
        }
        
        // Fall back to global config
        return config($key, $default);
    }
}
```

## Database Integration

### Migration Management
```php
// Component migrations
class CreateCacheTables extends Migration
{
    public function up()
    {
        Schema::create('cache_entries', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
}

// Run migrations on install
php component migrate
```

### Database Isolation
Each component uses prefixed tables:
```php
// Table naming convention
github_pull_requests
cache_entries
deploy_history
```

## Authentication Integration

### Shared Authentication
```php
// Central auth service
class AuthService
{
    public static function getToken(string $service): ?string
    {
        // Check multiple sources
        return env(strtoupper($service) . '_TOKEN')
            ?? config("services.{$service}.token")
            ?? self::promptForToken($service);
    }
}

// Components use shared auth
$token = AuthService::getToken('github');
```

### Token Storage
```php
// Secure token storage
class TokenStorage
{
    private static string $keyFile = '~/.shit/keys.json';
    
    public static function store(string $service, string $token): void
    {
        $keys = json_decode(File::get(self::$keyFile), true) ?? [];
        $keys[$service] = encrypt($token);
        File::put(self::$keyFile, json_encode($keys));
        chmod(self::$keyFile, 0600); // Restrict access
    }
}
```

## Testing Integration

### Integration Tests
```php
test('components work together', function () {
    // Install components
    $this->artisan('install github')->assertSuccessful();
    $this->artisan('install deploy')->assertSuccessful();
    
    // Test integration
    $this->artisan('github:pr:create "Test"')->assertSuccessful();
    
    // Verify event was emitted
    expect(EventBusService::last('github.pr.created'))->not->toBeNull();
    
    // Verify deploy component reacted
    expect(EventBusService::last('deploy.staged'))->not->toBeNull();
});
```

### Mock Services
```php
test('handles missing services gracefully', function () {
    // Mock missing service
    ServiceRegistry::mock('cache', null);
    
    // Command should still work
    $this->artisan('deploy production')
        ->expectsOutput('Warning: Cache service not available')
        ->assertSuccessful();
});
```

## Dependency Management

### Component Dependencies
```json
{
    "requires": {
        "shit-git": "^2.0",     // Requires git component
        "shit-cache": "^1.0"    // Requires cache component
    },
    "suggests": {
        "shit-notify": "^1.0"   // Optional notification support
    }
}
```

### Dependency Resolution
```php
// Check dependencies before operations
class DeployCommand extends Command
{
    public function handle()
    {
        // Required dependency
        if (!ComponentRegistry::has('git')) {
            $this->error('Git component required');
            $this->info('Install with: php 💩 install git');
            return self::FAILURE;
        }
        
        // Optional dependency
        if (ComponentRegistry::has('notify')) {
            $this->notify = ServiceRegistry::get('notify');
        }
        
        // Continue with deployment
    }
}
```

## Upgrade Integration

### Version Compatibility
```php
// Check version compatibility
class VersionChecker
{
    public static function isCompatible(string $component, string $required): bool
    {
        $installed = ComponentRegistry::version($component);
        return version_compare($installed, $required, '>=');
    }
}
```

### Upgrade Hooks
```json
{
    "hooks": {
        "pre-upgrade": "php component backup",
        "post-upgrade": "php component migrate"
    }
}
```

## Performance Integration

### Lazy Loading
```php
// Load components only when needed
class ComponentLoader
{
    private static array $loaded = [];
    
    public static function load(string $component): void
    {
        if (isset(self::$loaded[$component])) {
            return;
        }
        
        require_once "💩-components/{$component}/vendor/autoload.php";
        self::$loaded[$component] = true;
    }
}
```

### Caching Integration
```php
// Cache expensive operations
class GitHubService
{
    public function getPullRequests(): array
    {
        return Cache::remember('github.prs', 300, function() {
            return $this->api->get('/pulls');
        });
    }
}
```

## Security Integration

### Permission System
```php
// Component permissions
class PermissionManager
{
    public static function can(string $component, string $action): bool
    {
        $permissions = config("permissions.{$component}", []);
        return in_array($action, $permissions);
    }
}
```

### Sandboxing
```php
// Restrict component access
class Sandbox
{
    public static function run(string $component, callable $callback)
    {
        // Restrict file access
        $sandbox = new FileSandbox("💩-components/{$component}");
        
        // Restrict network access
        $sandbox->allowHosts(['api.github.com']);
        
        // Run in sandbox
        return $sandbox->execute($callback);
    }
}
```

## Monitoring Integration

### Health Checks
```php
// Component health monitoring
class HealthCheck
{
    public static function check(string $component): array
    {
        return [
            'status' => ComponentRegistry::isActive($component),
            'version' => ComponentRegistry::version($component),
            'memory' => memory_get_usage(),
            'last_run' => EventBusService::lastEvent($component)
        ];
    }
}
```

### Metrics Collection
```php
// Collect liberation metrics
class MetricsCollector
{
    public static function record(string $component, array $metrics): void
    {
        Storage::append(
            "metrics/{$component}.jsonl",
            json_encode([
                'timestamp' => now()->toIso8601String(),
                'metrics' => $metrics
            ]) . "\n"
        );
    }
}
```

## Best Practices

### 1. Loose Coupling
Components should work without depending on others:
```php
// Good: Check if service exists
if (ServiceRegistry::has('cache')) {
    $this->useCache();
} else {
    $this->useFileStorage();
}

// Bad: Assume service exists
$cache = ServiceRegistry::get('cache'); // May fail!
```

### 2. Graceful Degradation
```php
try {
    $result = ServiceRegistry::get('ai')->complete($prompt);
} catch (ServiceNotFoundException $e) {
    $this->warn('AI service not available, using basic completion');
    $result = $this->basicComplete($prompt);
}
```

### 3. Event Documentation
```php
/**
 * Emits: github.pr.created
 * Data: [
 *   'pr_number' => int,
 *   'title' => string,
 *   'url' => string
 * ]
 */
public function createPullRequest(): void
{
    // ...
}
```

### 4. Version Your APIs
```php
// Support multiple API versions
class GitHubService
{
    public function getPullRequests(int $version = 3): array
    {
        return match($version) {
            2 => $this->getPullRequestsV2(),
            3 => $this->getPullRequestsV3(),
            default => throw new UnsupportedVersionException($version)
        };
    }
}
```

## Summary

THE SHIT's integration architecture enables:
- **Seamless component cooperation** without tight coupling
- **Event-driven communication** for loose integration
- **Service discovery** for optional dependencies
- **Graceful degradation** when components are missing
- **Progressive enhancement** as more components are added

The integration layer is what transforms individual components into a cohesive ecosystem.

---

*Integration is like plumbing - when it works well, THE SHIT just flows.*