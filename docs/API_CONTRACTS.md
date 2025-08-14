# 📜 THE SHIT API Contracts

## Overview

API contracts define how components communicate with each other and with the core system. These contracts ensure compatibility, reliability, and predictability across THE SHIT ecosystem.

## Core Contracts

### Command Contract

Every command MUST implement this interface:

```php
interface CommandContract
{
    /**
     * Execute the command logic
     * @return int Exit code (0 for success, non-zero for failure)
     */
    public function executeCommand(): int;
    
    /**
     * Get data for output formatting
     * @return array Data to be formatted
     */
    public function getData(): array;
    
    /**
     * Output data in terminal format
     * @param array $data Data to output
     * @return int Exit code
     */
    public function outputTerminal(array $data): int;
    
    /**
     * Get liberation metrics for this command
     * @return array Liberation metrics
     */
    public function getLiberationMetrics(): array;
}
```

### Component Contract

Every component MUST provide:

```php
interface ComponentContract
{
    /**
     * Get component manifest
     * @return array Component metadata
     */
    public function getManifest(): array;
    
    /**
     * Get available commands
     * @return array Command definitions
     */
    public function getCommands(): array;
    
    /**
     * Get component version
     * @return string Semantic version
     */
    public function getVersion(): string;
    
    /**
     * Check if component is healthy
     * @return bool Health status
     */
    public function isHealthy(): bool;
}
```

## Event Contracts

### Event Structure

All events MUST follow this structure:

```php
interface EventContract
{
    /**
     * Event name in format: component.resource.action
     */
    public string $name;
    
    /**
     * Event data payload
     */
    public array $data;
    
    /**
     * ISO 8601 timestamp
     */
    public string $timestamp;
    
    /**
     * Originating component
     */
    public string $component;
    
    /**
     * Optional correlation ID for tracing
     */
    public ?string $correlationId;
}
```

### Standard Events

#### Lifecycle Events
```php
// Component lifecycle
'component.installed' => ['name' => string, 'version' => string]
'component.updated' => ['name' => string, 'from' => string, 'to' => string]
'component.removed' => ['name' => string]

// Command lifecycle  
'command.started' => ['command' => string, 'args' => array]
'command.completed' => ['command' => string, 'exitCode' => int]
'command.failed' => ['command' => string, 'error' => string]
```

#### Resource Events
```php
// CRUD operations
'resource.created' => ['type' => string, 'id' => mixed, 'data' => array]
'resource.updated' => ['type' => string, 'id' => mixed, 'changes' => array]
'resource.deleted' => ['type' => string, 'id' => mixed]
'resource.read' => ['type' => string, 'id' => mixed]
```

#### Integration Events
```php
// External service events
'api.request' => ['service' => string, 'endpoint' => string, 'method' => string]
'api.response' => ['service' => string, 'status' => int, 'duration' => float]
'api.error' => ['service' => string, 'error' => string, 'code' => int]
```

## Service Contracts

### Cache Service

```php
interface CacheContract
{
    /**
     * Get value from cache
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed Cached value or default
     */
    public function get(string $key, mixed $default = null): mixed;
    
    /**
     * Store value in cache
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds
     * @return bool Success status
     */
    public function put(string $key, mixed $value, int $ttl = 3600): bool;
    
    /**
     * Remove value from cache
     * @param string $key Cache key
     * @return bool Success status
     */
    public function forget(string $key): bool;
    
    /**
     * Clear all cached values
     * @return bool Success status
     */
    public function flush(): bool;
    
    /**
     * Get or store value
     * @param string $key Cache key
     * @param int $ttl Time to live
     * @param callable $callback Callback to generate value
     * @return mixed Cached or generated value
     */
    public function remember(string $key, int $ttl, callable $callback): mixed;
}
```

### Storage Service

```php
interface StorageContract
{
    /**
     * Read file contents
     * @param string $path File path
     * @return string|null File contents or null if not found
     */
    public function get(string $path): ?string;
    
    /**
     * Write file contents
     * @param string $path File path
     * @param string $contents File contents
     * @return bool Success status
     */
    public function put(string $path, string $contents): bool;
    
    /**
     * Check if file exists
     * @param string $path File path
     * @return bool Existence status
     */
    public function exists(string $path): bool;
    
    /**
     * Delete file
     * @param string $path File path
     * @return bool Success status
     */
    public function delete(string $path): bool;
    
    /**
     * List files in directory
     * @param string $directory Directory path
     * @return array File paths
     */
    public function files(string $directory): array;
}
```

### Notification Service

```php
interface NotificationContract
{
    /**
     * Send notification
     * @param string $channel Channel name (slack, email, etc)
     * @param string $message Notification message
     * @param array $options Additional options
     * @return bool Success status
     */
    public function send(string $channel, string $message, array $options = []): bool;
    
    /**
     * Send to multiple channels
     * @param array $channels Channel names
     * @param string $message Notification message
     * @return array Results per channel
     */
    public function broadcast(array $channels, string $message): array;
}
```

## Data Contracts

### Response Format

All API responses MUST follow this structure:

```php
interface ResponseContract
{
    /**
     * Success status
     */
    public bool $success;
    
    /**
     * Response data (null on error)
     */
    public ?array $data;
    
    /**
     * Error message (null on success)
     */
    public ?string $error;
    
    /**
     * Additional metadata
     */
    public array $meta;
}
```

Example responses:

```php
// Success response
[
    'success' => true,
    'data' => ['id' => 123, 'name' => 'Example'],
    'error' => null,
    'meta' => ['duration' => 0.123, 'cached' => false]
]

// Error response
[
    'success' => false,
    'data' => null,
    'error' => 'Resource not found',
    'meta' => ['code' => 404, 'trace_id' => 'abc123']
]
```

### Pagination Contract

```php
interface PaginationContract
{
    /**
     * Current page items
     */
    public array $items;
    
    /**
     * Current page number (1-based)
     */
    public int $currentPage;
    
    /**
     * Items per page
     */
    public int $perPage;
    
    /**
     * Total items across all pages
     */
    public int $total;
    
    /**
     * Total number of pages
     */
    public int $lastPage;
    
    /**
     * Next page URL (null if last page)
     */
    public ?string $nextPageUrl;
    
    /**
     * Previous page URL (null if first page)
     */
    public ?string $prevPageUrl;
}
```

## Configuration Contracts

### Component Configuration

```php
interface ConfigContract
{
    /**
     * Get configuration value
     * @param string $key Dot notation key
     * @param mixed $default Default value
     * @return mixed Configuration value
     */
    public function get(string $key, mixed $default = null): mixed;
    
    /**
     * Set configuration value
     * @param string $key Dot notation key
     * @param mixed $value Value to set
     * @return void
     */
    public function set(string $key, mixed $value): void;
    
    /**
     * Check if configuration exists
     * @param string $key Dot notation key
     * @return bool Existence status
     */
    public function has(string $key): bool;
}
```

### Environment Variables

Required environment variables MUST be documented:

```php
interface EnvironmentContract
{
    /**
     * Get required environment variables
     * @return array Variable definitions
     */
    public function getRequiredEnv(): array;
    
    /**
     * Get optional environment variables
     * @return array Variable definitions with defaults
     */
    public function getOptionalEnv(): array;
    
    /**
     * Validate environment
     * @return array Validation errors (empty if valid)
     */
    public function validateEnv(): array;
}
```

## Hook Contracts

### Lifecycle Hooks

```php
interface HookContract
{
    /**
     * Called before command execution
     * @param array $context Execution context
     * @return bool Continue execution
     */
    public function beforeExecute(array $context): bool;
    
    /**
     * Called after command execution
     * @param array $context Execution context
     * @param int $exitCode Command exit code
     * @return void
     */
    public function afterExecute(array $context, int $exitCode): void;
    
    /**
     * Called on error
     * @param array $context Execution context
     * @param \Throwable $error Error that occurred
     * @return bool Suppress error
     */
    public function onError(array $context, \Throwable $error): bool;
}
```

## Testing Contracts

### Test Assertions

```php
interface TestContract
{
    /**
     * Assert command succeeds
     * @param string $command Command to run
     * @param array $args Command arguments
     * @return void
     */
    public function assertCommandSucceeds(string $command, array $args = []): void;
    
    /**
     * Assert command fails
     * @param string $command Command to run
     * @param array $args Command arguments
     * @param int $expectedCode Expected exit code
     * @return void
     */
    public function assertCommandFails(string $command, array $args, int $expectedCode = 1): void;
    
    /**
     * Assert event was emitted
     * @param string $event Event name
     * @param array $data Expected data (partial match)
     * @return void
     */
    public function assertEventEmitted(string $event, array $data = []): void;
}
```

## Migration Contracts

### Database Migrations

```php
interface MigrationContract
{
    /**
     * Run migration
     * @return void
     */
    public function up(): void;
    
    /**
     * Reverse migration
     * @return void
     */
    public function down(): void;
    
    /**
     * Get migration dependencies
     * @return array Migration class names
     */
    public function dependencies(): array;
}
```

## Orchestration Contracts

### Instance Registration

```php
interface OrchestrationContract
{
    /**
     * Register instance with orchestrator
     * @param string $instanceId Instance identifier
     * @param array $metadata Instance metadata
     * @return bool Registration success
     */
    public function register(string $instanceId, array $metadata): bool;
    
    /**
     * Update instance status
     * @param string $instanceId Instance identifier
     * @param array $status Status update
     * @return bool Update success
     */
    public function update(string $instanceId, array $status): bool;
    
    /**
     * Release instance and locks
     * @param string $instanceId Instance identifier
     * @return bool Release success
     */
    public function release(string $instanceId): bool;
    
    /**
     * Check file availability
     * @param string $file File path
     * @param string $action Action type (read|edit|create)
     * @return array Availability status
     */
    public function checkFile(string $file, string $action): array;
}
```

## Versioning Strategy

### Semantic Versioning

All components MUST follow semantic versioning:

```
MAJOR.MINOR.PATCH

MAJOR: Breaking changes to contracts
MINOR: New contracts or optional parameters
PATCH: Bug fixes, no contract changes
```

### Breaking Changes

Breaking changes require major version bump:
- Removing methods from contracts
- Changing method signatures
- Changing required parameters
- Changing return types
- Removing events

### Backward Compatibility

Non-breaking additions:
- Adding optional parameters with defaults
- Adding new methods to contracts
- Adding new events
- Adding new optional configuration

### Deprecation Policy

```php
/**
 * @deprecated since 2.0, will be removed in 3.0
 * @see NewContract::newMethod()
 */
public function oldMethod(): void
{
    trigger_error('Method deprecated', E_USER_DEPRECATED);
    return $this->newMethod();
}
```

## Contract Validation

### Runtime Validation

```php
class ContractValidator
{
    public static function validate(object $implementation, string $contract): void
    {
        if (!$implementation instanceof $contract) {
            throw new ContractViolationException(
                get_class($implementation) . ' must implement ' . $contract
            );
        }
        
        // Validate method signatures
        $reflection = new ReflectionClass($contract);
        foreach ($reflection->getMethods() as $method) {
            if (!method_exists($implementation, $method->getName())) {
                throw new ContractViolationException(
                    'Missing required method: ' . $method->getName()
                );
            }
        }
    }
}
```

### Testing Contract Compliance

```php
test('component implements required contracts', function () {
    $component = new GitHubComponent();
    
    expect($component)->toImplement(ComponentContract::class);
    expect($component)->toImplement(EventContract::class);
    expect($component)->toHaveMethod('getManifest');
    expect($component->getManifest())->toHaveKeys(['name', 'version', 'commands']);
});
```

## Contract Documentation

### PHPDoc Standards

```php
interface ExampleContract
{
    /**
     * Short description of what method does
     *
     * Longer description with more details about behavior,
     * side effects, and important notes.
     *
     * @param string $param Parameter description
     * @param array $options {
     *     Optional. Array of options.
     *
     *     @type string $key Description of key
     *     @type int $count Description of count
     * }
     * @return array {
     *     Return value structure.
     *
     *     @type bool $success Success status
     *     @type string $message Result message
     * }
     * @throws ExceptionType When this exception occurs
     * @since 1.0.0
     * @see RelatedContract::relatedMethod()
     * @example
     * $result = $service->exampleMethod('value', [
     *     'key' => 'value',
     *     'count' => 10
     * ]);
     */
    public function exampleMethod(string $param, array $options = []): array;
}
```

## Contract Evolution

### Adding Contracts

1. Define contract interface
2. Document thoroughly
3. Implement in core
4. Provide reference implementation
5. Add tests
6. Update documentation

### Modifying Contracts

1. Assess impact (breaking vs non-breaking)
2. Deprecate old methods if needed
3. Provide migration path
4. Update all implementations
5. Document changes in CHANGELOG

### Removing Contracts

1. Deprecate in minor version
2. Provide alternative
3. Wait for major version
4. Remove deprecated code
5. Update all dependents

## Summary

API contracts are the backbone of THE SHIT's component ecosystem. They ensure:

- **Compatibility**: Components work together seamlessly
- **Reliability**: Predictable behavior across versions
- **Maintainability**: Clear boundaries and responsibilities
- **Evolvability**: Safe upgrades and migrations

Every component must respect these contracts. Every contract must serve liberation.

---

*Good contracts are like good plumbing - invisible when working, obvious when broken.*