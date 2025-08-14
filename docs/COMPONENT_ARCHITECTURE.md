# 🧩 THE SHIT Component Architecture

## Overview

THE SHIT components are self-contained, GitHub-distributed packages that extend the core framework's capabilities. Each component is a complete Laravel Zero application that can run standalone or integrate with the main CLI.

## Component Lifecycle

### 1. Discovery
Components are discovered through GitHub:
```bash
# Components must have the 'shit-component' topic
curl -s "https://api.github.com/search/repositories?q=topic:shit-component"
```

### 2. Installation
```bash
# Install from GitHub
php 💩 install github

# Install specific version
php 💩 install github --version=2.0.0

# Install from branch
php 💩 install github --branch=develop
```

### 3. Registration
Components auto-register on installation:
```php
// ComponentServiceProvider discovers components
foreach (glob('💩-components/*/💩.json') as $manifest) {
    $this->registerComponent($manifest);
}
```

### 4. Execution
Commands are proxied through the main CLI:
```php
// Main CLI proxies to component
php 💩 github:pr:create
// Executes: php 💩-components/github/component pr:create
```

## Component Structure

### Required Files

```
component-name/
├── 💩.json                 # Component manifest (REQUIRED)
├── component              # Laravel Zero executable (REQUIRED)
├── composer.json          # PHP dependencies (REQUIRED)
└── README.md             # Documentation (REQUIRED)
```

### Standard Structure

```
component-name/
├── 💩.json                 # Component manifest
├── app/
│   ├── Commands/          # CLI commands
│   ├── Services/          # Business logic
│   ├── Providers/         # Service providers
│   └── Contracts/         # Interfaces
├── config/
│   └── app.php           # Laravel Zero config
├── tests/
│   ├── Feature/          # Feature tests
│   └── Unit/             # Unit tests
├── docs/
│   └── knowledge/        # Component knowledge base
├── bin/
│   └── component-name    # Optional binary
└── vendor/               # Isolated dependencies
```

## Component Manifest

The `💩.json` file defines component metadata:

```json
{
    "name": "github",
    "description": "GitHub integration for THE SHIT",
    "version": "2.1.0",
    "shit_acronym": "Streamlined Hub Integration Tool",
    "author": {
        "name": "Jordan Partridge",
        "email": "jordan@partridge.rocks"
    },
    "repository": "https://github.com/jordanpartridge/shit-github",
    "commands": {
        "github:pr": "Manage pull requests",
        "github:pr:create": "Create a new pull request",
        "github:pr:review": "Review pull requests",
        "github:issue": "Manage issues",
        "github:release": "Manage releases"
    },
    "requires": {
        "php": "^8.2",
        "the-shit": "^1.0"
    },
    "suggests": {
        "shit-git": "Enhanced git operations"
    },
    "config": {
        "env": {
            "GITHUB_TOKEN": "GitHub personal access token",
            "GITHUB_ORG": "Default organization"
        }
    },
    "events": {
        "emits": [
            "github.pr.created",
            "github.pr.merged",
            "github.issue.created"
        ],
        "listens": [
            "git.commit.created",
            "deploy.completed"
        ]
    },
    "hooks": {
        "post-install": "php component setup",
        "pre-uninstall": "php component cleanup"
    }
}
```

## Component Types

### 1. Service Components
Provide services to other components:
```php
// cache component provides caching service
class CacheComponent {
    public function remember($key, $ttl, $callback) {
        // Caching logic
    }
}
```

### 2. Integration Components
Connect to external services:
```php
// github component integrates with GitHub API
class GitHubComponent {
    public function createPullRequest($title, $body) {
        return Http::withToken($this->token)
            ->post('/repos/owner/repo/pulls', [...]);
    }
}
```

### 3. Utility Components
Provide utility functions:
```php
// format component provides formatters
class FormatComponent {
    public function table($data) { }
    public function json($data) { }
    public function csv($data) { }
}
```

### 4. Workflow Components
Orchestrate complex workflows:
```php
// deploy component manages deployments
class DeployComponent {
    public function deploy($environment) {
        $this->backup();
        $this->test();
        $this->push();
        $this->verify();
    }
}
```

## Inter-Component Communication

### 1. Event Bus
Components communicate through events:
```php
// Component A emits event
EventBusService::emit('user.registered', [
    'user_id' => 123,
    'email' => 'user@example.com'
]);

// Component B listens
EventBusService::on('user.registered', function($data) {
    $this->sendWelcomeEmail($data['email']);
});
```

### 2. Service Registry
Components register and discover services:
```php
// Cache component registers itself
ServiceRegistry::register('cache', CacheService::class);

// Other components use it
$cache = ServiceRegistry::get('cache');
$value = $cache->remember('key', 3600, fn() => expensive());
```

### 3. Command Delegation
Components can delegate to each other:
```php
// Deploy component uses git component
class DeployCommand extends Command {
    public function handle() {
        // Delegate to git component
        $this->call('git:commit', ['message' => 'Deploy']);
        
        // Continue with deployment
        $this->deploy();
    }
}
```

### 4. Shared Storage
Components share data through storage:
```php
// Write to shared storage
Storage::put('components/shared/data.json', json_encode($data));

// Read from shared storage
$data = json_decode(Storage::get('components/shared/data.json'));
```

## Component Commands

### Command Structure
All component commands should extend base classes:

```php
namespace App\Commands;

use App\Commands\BaseCommand; // Or ConduitCommand

class GitHubPrCommand extends BaseCommand
{
    protected $signature = 'pr:create 
                            {title : PR title}
                            {--draft : Create as draft}';
    
    protected function executeCommand(): int
    {
        // Command logic
        return self::SUCCESS;
    }
    
    public function getData(): array
    {
        // Return data for formatting
        return ['pr_number' => 123];
    }
    
    public function outputTerminal(array $data): int
    {
        $this->info("Created PR #{$data['pr_number']}");
        return 0;
    }
}
```

### Universal Output Support
Commands must support multiple output formats:

```php
class DataCommand extends BaseCommand
{
    public function getData(): array
    {
        return [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25]
        ];
    }
    
    public function outputTerminal(array $data): int
    {
        $this->table(['Name', 'Age'], $data);
        return 0;
    }
    
    public function outputJson(array $data): int
    {
        $this->line(json_encode($data));
        return 0;
    }
}
```

## Component Services

### Service Architecture
Services contain business logic:

```php
namespace App\Services;

class GitHubService
{
    private Http $client;
    
    public function __construct(private string $token)
    {
        $this->client = Http::withToken($token)
            ->baseUrl('https://api.github.com');
    }
    
    public function createPullRequest(array $data): array
    {
        $response = $this->client->post('/repos/owner/repo/pulls', $data);
        
        if ($response->failed()) {
            throw new GitHubException($response->json('message'));
        }
        
        return $response->json();
    }
}
```

### Service Registration
Register services in providers:

```php
class GitHubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GitHubService::class, function ($app) {
            return new GitHubService(
                token: config('github.token')
            );
        });
    }
}
```

## Component Testing

### Test Structure
```
tests/
├── Feature/
│   ├── Commands/
│   │   └── GitHubPrCommandTest.php
│   └── Integration/
│       └── GitHubIntegrationTest.php
└── Unit/
    └── Services/
        └── GitHubServiceTest.php
```

### Testing Commands
```php
test('creates pull request', function () {
    $this->artisan('pr:create', [
        'title' => 'New feature',
        '--draft' => true
    ])
    ->expectsOutput('Created PR #123')
    ->assertSuccessful();
});
```

### Testing Services
```php
test('github service creates PR', function () {
    $service = new GitHubService('fake-token');
    
    Http::fake([
        'github.com/*' => Http::response(['number' => 123])
    ]);
    
    $pr = $service->createPullRequest([
        'title' => 'Test PR',
        'body' => 'Test body'
    ]);
    
    expect($pr['number'])->toBe(123);
});
```

## Component Dependencies

### Isolation Principle
Each component has its own vendor directory:
```bash
💩-components/github/vendor/  # GitHub's dependencies
💩-components/deploy/vendor/  # Deploy's dependencies
```

### Dependency Management
```json
{
    "require": {
        "php": "^8.2",
        "laravel-zero/framework": "^10.0",
        "guzzlehttp/guzzle": "^7.0"
    },
    "require-dev": {
        "pestphp/pest": "^2.0"
    }
}
```

### Shared Interfaces
Use interfaces package for contracts:
```json
{
    "require": {
        "jordanpartridge/conduit-interfaces": "^1.0"
    }
}
```

## Component Configuration

### Environment Variables
Components use prefixed env vars:
```bash
GITHUB_TOKEN=ghp_xxxxx
GITHUB_ORG=my-org
GITHUB_REPO=my-repo
```

### Configuration Files
```php
// config/github.php
return [
    'token' => env('GITHUB_TOKEN'),
    'org' => env('GITHUB_ORG'),
    'repo' => env('GITHUB_REPO'),
    'api_version' => env('GITHUB_API_VERSION', 'v3'),
];
```

### User Configuration
Allow user overrides:
```php
// Check for user config first
$config = File::exists($userConfig) 
    ? include $userConfig 
    : include __DIR__ . '/config/default.php';
```

## Component Events

### Emitting Events
```php
class GitHubPrCommand extends BaseCommand
{
    protected function executeCommand(): int
    {
        $pr = $this->github->createPullRequest([...]);
        
        // Emit event
        EventBusService::emit('github.pr.created', [
            'pr_number' => $pr['number'],
            'title' => $pr['title'],
            'url' => $pr['html_url']
        ]);
        
        return self::SUCCESS;
    }
}
```

### Listening to Events
```php
class SlackComponent
{
    public function boot()
    {
        EventBusService::on('github.pr.created', function ($data) {
            $this->slack->send("New PR: {$data['title']} - {$data['url']}");
        });
    }
}
```

## Component Versioning

### Semantic Versioning
Follow semver strictly:
- MAJOR: Breaking changes
- MINOR: New features, backward compatible
- PATCH: Bug fixes

### Version Constraints
```json
{
    "requires": {
        "the-shit": "^1.0",      // Compatible with 1.x
        "shit-git": "~2.1.0",    // Compatible with 2.1.x
        "shit-cache": "2.0.0"    // Exact version
    }
}
```

### Upgrade Path
```bash
# Check for updates
php 💩 component:update --check

# Update specific component
php 💩 component:update github

# Update all components
php 💩 component:update --all
```

## Component Security

### Input Validation
Always validate input:
```php
protected function executeCommand(): int
{
    $file = $this->argument('file');
    
    if (!File::exists($file)) {
        $this->error("File not found: {$file}");
        return self::FAILURE;
    }
    
    if (!$this->isAllowedFile($file)) {
        $this->error("Access denied: {$file}");
        return self::FAILURE;
    }
    
    // Process file...
}
```

### API Key Management
Never hardcode secrets:
```php
// Bad
$token = 'ghp_hardcoded_token';

// Good
$token = env('GITHUB_TOKEN');

if (!$token) {
    $this->error('GITHUB_TOKEN not set');
    $this->info('Set it with: export GITHUB_TOKEN=your_token');
    return self::FAILURE;
}
```

### Sandboxing
Components run in restricted context:
```php
// Components can't access parent directories
$component->restrictToPath(base_path('💩-components/github'));
```

## Component Best Practices

### 1. Single Responsibility
Each component does one thing well:
- ✅ `shit-github` - GitHub integration
- ❌ `shit-everything` - Does GitHub, Slack, Deploy, etc.

### 2. Progressive Disclosure
Start simple, allow complexity:
```bash
# Simple usage
php 💩 deploy production

# Advanced usage
php 💩 deploy production --strategy=blue-green --backup --notify
```

### 3. Graceful Degradation
Handle missing dependencies:
```php
if (!$this->hasComponent('cache')) {
    $this->warn('Cache component not installed, using file cache');
    $this->useFileCache();
}
```

### 4. Comprehensive Documentation
Every component needs:
- README.md with examples
- Command help text
- Inline code documentation
- Knowledge base entries

### 5. Liberation Metrics
Track the value provided:
```php
public function getLiberationMetrics(): array
{
    return [
        'time_saved' => '10 minutes per PR',
        'automation_rate' => '95%',
        'error_reduction' => '80%'
    ];
}
```

## Component Development Workflow

### 1. Scaffold
```bash
php 💩 component:scaffold my-component
```

### 2. Develop
```bash
cd 💩-components/my-component
composer install
./vendor/bin/pest
```

### 3. Test Integration
```bash
php 💩 my-component:test
```

### 4. Document
```bash
php 💩 knowledge:capture "How my-component works" --tags=component,architecture
```

### 5. Publish
```bash
git push origin main
git tag v1.0.0
git push --tags
# Add 'shit-component' topic on GitHub
```

## Summary

THE SHIT's component architecture enables:
- **Modularity**: Add features without bloating core
- **Independence**: Components work standalone
- **Collaboration**: Components work together via events
- **Distribution**: GitHub-based package management
- **Liberation**: Each component reduces tedious work

Components are the building blocks of THE SHIT ecosystem, turning individual tools into a comprehensive development platform.

---

*Remember: Good components are like good shit - they get the job done and you're glad they exist.*