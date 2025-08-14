# 💩 THE SHIT Core Principles

## Philosophy

THE SHIT (Scaling Humans Into Tomorrow) is built on fundamental principles that guide every aspect of its architecture, from individual components to system-wide interactions.

## Core Principles

### 1. 🚀 Liberation First
**Every feature must liberate developers from tedious work**
- Automate the boring stuff
- Reduce cognitive load
- Measure liberation metrics (time saved, complexity reduced)
- If it doesn't make developers' lives easier, it doesn't belong

### 2. 🧩 Component Independence
**Components must be self-contained and independently valuable**
- Each component works standalone
- No inter-component dependencies at the core level
- Components enhance but don't require each other
- GitHub-distributed for maximum portability

### 3. 🤝 Human-AI Collaboration
**Seamless interaction between humans and AI agents**
```php
// Every command adapts to its user
if ($this->isNonInteractiveMode()) {
    return $this->jsonResponse($data);  // AI/CI mode
} else {
    $this->table($headers, $data);      // Human mode
}
```

### 4. 💩 Embrace the Brand
**The 💩 emoji is not just branding, it's philosophy**
- We turn shit (tedious work) into gold (automation)
- Every component has a creative SHIT acronym
- Fun and functionality go hand in hand
- If it's not fun to use, we're doing it wrong

### 5. 🎯 Zero Configuration Start
**Components must work immediately after installation**
- Smart defaults over configuration
- Progressive disclosure of complexity
- Convention over configuration
- But allow deep customization when needed

### 6. 📦 GitHub as Package Manager
**Components are distributed via GitHub, not traditional package managers**
- Use topics for discovery (`shit-component`)
- Version control built-in
- Social coding enables community contributions
- No central registry to maintain or fail

### 7. 🔄 Event-Driven Architecture
**Components communicate through events, not direct calls**
```php
// Components emit events
EventBusService::emit('component.action', $data);

// Other components can listen
EventBusService::on('component.action', function($data) {
    // React to the event
});
```

### 8. 🛡️ Defensive Programming
**Never trust, always verify**
- Check file existence before operations
- Validate all inputs
- Graceful degradation over hard failures
- Comprehensive error messages that help

### 9. 📊 Measurable Impact
**Every feature must demonstrate its value**
- Track liberation metrics
- Measure time saved
- Count automated tasks
- Show complexity reduction

### 10. 🌍 Universal Output Formats
**Support multiple output formats for different consumers**
```php
// Commands support multiple formats
--format=json    # For AI/API consumption
--format=table   # For human reading
--format=csv     # For data processing
--format=yaml    # For configuration
```

## Design Patterns

### Command Pattern
All commands extend `ConduitCommand` providing:
- Smart input methods (`smartText()`, `smartConfirm()`)
- Automatic mode detection (human/AI/CI)
- Consistent response formatting
- Non-interactive fallbacks

### Service Layer Pattern
Business logic lives in services, not commands:
```php
// Command is thin
class DeployCommand extends ConduitCommand {
    protected function executeCommand(): int {
        return $this->deployService->deploy($this->argument('target'));
    }
}
```

### Repository Pattern for Components
Components are treated as repositories:
- Discovered by topic
- Installed via git
- Updated through pulls
- Versioned with tags

### Event Sourcing for History
All significant actions are recorded as events:
```php
EventBusService::emit('deploy.started', [
    'component' => 'api',
    'environment' => 'production',
    'timestamp' => now()
]);
```

## Component Standards

### Manifest Structure
Every component must have `💩.json`:
```json
{
    "name": "component-name",
    "description": "What this component does",
    "version": "1.0.0",
    "shit_acronym": "Specific Helpful Implementation Tool",
    "commands": {
        "component:action": "Description of action"
    },
    "requires": {
        "php": "^8.2"
    }
}
```

### Directory Structure
```
component-name/
├── 💩.json              # Component manifest
├── app/
│   ├── Commands/       # CLI commands
│   └── Services/       # Business logic
├── config/            # Configuration files
├── tests/             # Pest tests
└── docs/              # Component documentation
```

### Command Naming
Commands follow namespace pattern:
```
component:action
component:resource:action
```

Examples:
- `github:pr:create`
- `deploy:rollback`
- `cache:clear`

## Integration Patterns

### 1. Proxy Command Pattern
Main CLI proxies to component commands:
```php
// Main CLI registers proxy
Artisan::command('github:pr', function() {
    return Process::run(['php', $componentPath, 'pr', ...$args]);
});
```

### 2. Event Bus Pattern
Components communicate via events:
```php
// Component A emits
EventBusService::emit('user.created', $userData);

// Component B listens
EventBusService::on('user.created', function($userData) {
    $this->sendWelcomeEmail($userData);
});
```

### 3. Service Discovery Pattern
Components announce their capabilities:
```php
// Component registers what it provides
ServiceRegistry::register('email', EmailService::class);

// Other components can discover
$emailService = ServiceRegistry::get('email');
```

## Error Handling Philosophy

### Fail Gracefully
Never let errors crash the entire system:
```php
try {
    $component->execute();
} catch (ComponentException $e) {
    $this->warn("Component failed: {$e->getMessage()}");
    $this->suggestFallback();
}
```

### Helpful Error Messages
Errors should guide users to solutions:
```php
if (!File::exists($configFile)) {
    $this->error("Config file not found: {$configFile}");
    $this->info("Create one with: php 💩 config:init");
    $this->info("Or copy the example: cp config.example.php config.php");
}
```

### Progressive Disclosure
Don't overwhelm with details unless requested:
```php
$this->error("Command failed");

if ($this->option('verbose')) {
    $this->line("Stack trace:");
    $this->line($exception->getTraceAsString());
}
```

## Performance Principles

### Lazy Loading
Don't load what you don't need:
```php
// Components are loaded only when accessed
if ($this->hasComponent('cache')) {
    $this->loadComponent('cache');
}
```

### Async When Possible
Use background processing for long tasks:
```php
Process::start("php 💩 component:install {$component}")
    ->onOutput(fn($output) => $this->line($output));
```

### Cache Aggressively
Cache expensive operations:
```php
return Cache::remember('components.list', 3600, function() {
    return $this->githubService->searchComponents();
});
```

## Testing Standards

### Test Everything That Matters
- Commands have feature tests
- Services have unit tests
- Components have integration tests
- Critical paths have end-to-end tests

### Test Both Modes
Always test human and AI modes:
```php
test('works in human mode', function() {
    $this->artisan('deploy')
        ->expectsQuestion('Which environment?', 'staging')
        ->assertSuccessful();
});

test('works in AI mode', function() {
    $this->artisan('deploy staging --no-interaction')
        ->assertSuccessful();
});
```

## Documentation Standards

### Self-Documenting Code
Code should be readable without comments:
```php
// Bad
$x = $u->gA(); // get attributes

// Good
$attributes = $user->getAttributes();
```

### Document the Why, Not the What
```php
// Bad: This increments the counter
$counter++;

// Good: Increment to account for header row
$counter++;
```

### Examples Over Explanations
Show, don't just tell:
```php
/**
 * Format output based on type
 * 
 * Examples:
 *   $this->format(['name' => 'John'], 'json')  // {"name":"John"}
 *   $this->format($data, 'table')              // ASCII table
 *   $this->format($data, 'csv')                // CSV output
 */
```

## Security Principles

### Never Trust User Input
Always validate and sanitize:
```php
$file = $this->argument('file');

if (!File::exists($file)) {
    throw new FileNotFoundException($file);
}

if (!$this->isAllowedPath($file)) {
    throw new SecurityException("Access denied: {$file}");
}
```

### Principle of Least Privilege
Components get only the permissions they need:
```php
// Components can't access parent directories
$component->setBasePath($componentPath);
$component->restrictToBasePath();
```

### Secure by Default
Security is not optional:
```php
// API calls always use HTTPS
Http::withOptions(['verify' => true])
    ->post($endpoint, $data);
```

## Evolution Principles

### Backward Compatibility
Never break existing functionality:
```php
// Mark deprecations, don't remove
#[Deprecated('Use deploy() instead')]
public function oldDeploy() {
    return $this->deploy();
}
```

### Progressive Enhancement
New features enhance, not replace:
```php
// Old way still works
$this->deploy('production');

// New way adds features
$this->deploy('production', ['strategy' => 'blue-green']);
```

### Community-Driven Development
- Issues drive priorities
- Pull requests are welcomed
- Documentation is a community effort
- Components can come from anyone

## The Liberation Metric

Every feature is measured by:
```
Liberation Score = (Time Saved × Frequency) / Complexity Added
```

If the score is negative, the feature is rejected.

## Summary

THE SHIT is not just a framework; it's a philosophy of developer liberation. Every line of code, every component, every feature serves one purpose: **making developers' lives better**.

We measure our success not in lines of code written, but in hours of tedious work eliminated.

---

*Remember: THE SHIT turns tedious work into automated gold. That's not just our tagline—it's our promise.*