# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

THE SHIT (Scaling Humans Into Tomorrow) is a Laravel Zero CLI framework built for Human-AI collaboration. The project uses 💩 emoji as its executable and follows component-based architecture with GitHub-distributed extensions.

## Key Commands

### Testing
```bash
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Feature/ExampleTest.php

# Run with coverage
./vendor/bin/pest --coverage
```

### Code Quality
```bash
# Run all quality checks with auto-fix
composer quality

# Run checks without auto-fix  
composer check

# Individual tools
composer pint         # Fix code style
composer pint:test    # Check code style without fixing
composer stan         # Run static analysis (Larastan level 5)
composer test         # Run tests
```

### Development
```bash
# Install dependencies
composer install

# Clear Laravel Zero cache
php 💩 cache:clear

# List all commands
php 💩 list

# Install a component
php 💩 install <component-name>

# Scaffold new component
php 💩 component:scaffold <name>
```

## Architecture

### Command Structure
All commands extend `App\Commands\ConduitCommand` which provides Human-AI collaboration patterns:

- **Smart Input Methods**: `smartText()`, `smartConfirm()`, `smartChoice()` - automatically adapt to interactive vs non-interactive modes
- **User Agent Detection**: Detects human, claude, ai, or ci agents via `CONDUIT_USER_AGENT` environment variable
- **Response Formatting**: `jsonResponse()` for AI agents, formatted output for humans
- **Non-Interactive Mode**: Commands gracefully fallback when `--no-interaction` or `-n` flags are used

### Component System
Components are GitHub-based packages installed to `💩-components/`:

- **Discovery**: Components are found via `shit-component` GitHub topic
- **Installation**: `php 💩 install <name>` fetches from GitHub repositories
- **Dynamic Registration**: `ComponentServiceProvider` discovers and registers component commands at runtime
- **Proxy Commands**: Component commands are proxied through the main executable with full argument passthrough
- **Manifest**: Each component has a `💩.json` file defining metadata, commands, and dependencies

#### 💩 THE SHIT Philosophy: Simple Commands Only
The delegation system enforces simplicity by design:
- **NO CUSTOM MODIFIERS**: Commands stay focused on their single purpose
- **GLOBAL FLAGS ONLY**: `--json`, `--no-interaction` are available everywhere
- **ONE ARGUMENT MAX**: Commands do one thing well
- **Pattern**: `orchestrator:status agent123` ✅ THE SHIT WAY
- **Pattern**: `orchestrator:assign "task description"` ✅ THE SHIT WAY
- **Anti-pattern**: `orchestrator:status --format=xml --verbose` ❌ TOO COMPLICATED

This isn't a limitation - it's THE SHIT keeping you honest. If your command needs complex options, you're probably doing too much. Break it into separate commands that each do one thing perfectly.

### Event System
Event-driven architecture via `EventBusService`:

- **Event Storage**: Events stored in `storage/events.jsonl` as JSON lines
- **Event Format**: `{component}.{event}` naming convention
- **Event Methods**: 
  - `EventBusService::emit()` - Publish events
  - `EventBusService::recent()` - Get recent events
  - `EventBusService::forComponent()` - Filter by component
  - `EventBusService::byEvent()` - Filter by event type

### Directory Structure
```
app/
  Commands/           # Core CLI commands extending ConduitCommand
  Providers/         # Service providers (AppServiceProvider, ComponentServiceProvider)
  Services/          # Core services (EventBusService, ActivityLogger)
  ValueObjects/      # Domain objects (Component, ComponentManifest)
💩-components/       # Installed components directory
config/             # Laravel Zero configuration
database/
  migrations/       # Database migrations for activity logging
tests/              # Pest test suites
```

## Architectural Principles

### Expert-Level Development Standards
- **NO WORKAROUNDS**: Fix issues at their root cause, not with temporary patches
- **NO TECHNICAL DEBT**: Every implementation should be production-ready
- **PROPER AUTHENTICATION**: Set up authentication systems correctly, not bypass them
- **DESIGN PATTERNS**: Use appropriate design patterns for maintainability
- **DEPENDENCY INJECTION**: Prefer DI over facades for testability
- **ERROR HANDLING**: Implement comprehensive error handling, not quick fixes
- **CONFIGURATION**: Externalize configuration properly (env vars, config files)

### Laravel Native Features
Always use Laravel/Laravel Zero native features over Symfony directly:
- Use `Illuminate\Support\Facades\Process` NOT `Symfony\Component\Process`
- Use Laravel Prompts (`warning()`, `info()`, `error()`) for CLI output
- Use Laravel's Http facade for API calls
- Use Laravel's File facade for filesystem operations

## Human-AI Collaboration Patterns

Commands automatically detect interaction mode and adapt behavior:

```php
// Detection happens automatically in ConduitCommand
if ($this->isNonInteractiveMode()) {
    return $this->jsonResponse(['data' => $result]);
}

// Human mode: display formatted output
$this->smartInfo('Operation completed');
```

## Component Development

### Component Manifest Structure
```json
{
    "name": "component-name",
    "description": "Component description",
    "version": "1.0.0",
    "shit_acronym": "Specific Helpful Implementation Tool",
    "commands": {
        "component:command": "Command description"
    },
    "requires": {
        "php": "^8.2"
    }
}
```

### GitHub Repository Requirements
- Must have `shit-component` topic for discovery
- Should follow naming convention for automatic detection
- Can specify version constraints or branch installation

## Code Quality Standards

### MANDATORY Before Every Commit
```bash
# Run complete quality check
composer quality      # Fixes code style and runs all checks

# Or check without auto-fix
composer check        # Tests code style without changing
```

### Laravel Pint Configuration
- **Standard**: PSR-12 with Laravel preset
- **Auto-fix**: Run `composer pint` to fix style issues
- **Check only**: Use `composer pint:test` to verify without changing

### Larastan Configuration
- **Level**: 5 (balanced strictness)
- **Config**: `phpstan.neon` in project root
- **Paths**: Analyzes `app/` and `config/` directories
- **Excludes**: `💩-components/*` and `vendor/*`

### Testing with Pest
- **Framework**: Pest (NOT PHPUnit directly)
- **Structure**: Feature tests in `tests/Feature/`, Unit tests in `tests/Unit/`
- **Coverage**: Aim for >80% on new code
- **Test both modes**: Human interactive AND AI/non-interactive

## Important Conventions

1. **Emoji Usage**: The 💩 emoji is the brand identity - use it consistently in filenames and component paths
2. **Error Handling**: Commands should fail gracefully with helpful messages in both human and AI modes
3. **Component Isolation**: Each component has its own vendor directory - never share dependencies
4. **Version Constraints**: Support semantic versioning in component requirements
5. **Branch Installation**: Support installing from branches with `--branch` option or `branch:name` version

## Commit Standards

- Use clear, concise commit messages
- NO Claude Code attribution in commits
- Format: `<type>: <description>` (e.g., `fix: resolve OAuth token refresh issue`)
- Keep commits atomic and focused
- Never include sensitive information or API keys