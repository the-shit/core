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
# Format code with Laravel Pint
./vendor/bin/pint

# Check formatting without fixing
./vendor/bin/pint --test
```

### Development
```bash
# Install dependencies
composer install

# Update dependencies
composer update

# Clear Laravel Zero cache
php 💩 cache:clear

# List all commands
php 💩 list
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
- **USE LARAVEL NATIVE**: Always use Laravel/Laravel Zero native features over Symfony directly
  - Use `Illuminate\Support\Facades\Process` NOT `Symfony\Component\Process`
  - Use Laravel Prompts (`warning()`, `info()`, `error()`) for CLI output
  - Use Laravel's Http facade for API calls
  - Use Laravel's File facade for filesystem operations

When encountering issues:
1. Identify the root cause
2. Design a proper solution
3. Implement it correctly the first time
4. Add tests to prevent regression
5. Document the solution

## Architecture

### Command Structure
All commands extend `App\Commands\ConduitCommand` which provides Human-AI collaboration patterns:

- **Smart Input Methods**: `smartText()`, `smartConfirm()`, `smartChoice()` - automatically adapt to interactive vs non-interactive modes
- **User Agent Detection**: Detects human, claude, ai, or ci agents via `CONDUIT_USER_AGENT` environment variable
- **Response Formatting**: `jsonResponse()` for AI agents, `smartInfo()` for humans

### Component System
Components are GitHub-based packages installed to `💩-components/`:

- **Installation**: `php 💩 component:install <name>` fetches from `S-H-I-T` GitHub organization
- **Scaffolding**: `php 💩 component:scaffold <name>` creates new component structure
- **Configuration**: `php 💩 component:config <name>` manages component settings
- **Manifest**: Each component has a `💩.json` file defining metadata and dependencies

### Directory Structure
```
app/Commands/           # Core CLI commands extending ConduitCommand
app/Providers/         # Service providers (AppServiceProvider, ComponentServiceProvider)
app/ValueObjects/      # Domain objects (Component, ComponentManifest)
💩-components/         # Installed components directory
config/               # Laravel Zero configuration
tests/               # Pest test suites
```

## Human-AI Collaboration Patterns

When developing commands, always implement both human and AI modes:

```php
// Detect mode
if ($this->isNonInteractiveMode()) {
    // AI mode: return structured data
    return $this->jsonResponse(['data' => $result]);
}

// Human mode: display formatted output
$this->smartInfo('Operation completed');
$this->table(['Column'], $data);
```

## Component Development

### Creating a New Component
1. Use scaffold command: `php 💩 component:scaffold my-component`
2. Edit generated `💩.json` manifest with proper metadata
3. Implement commands in `app/Commands/` directory
4. Register in component's service provider

### Component Manifest Structure
```json
{
    "name": "component-name",
    "description": "Component description",
    "version": "1.0.0",
    "shit_acronym": "Specific Helpful Implementation Tool",
    "requires": {
        "php": "^8.2"
    }
}
```

## Testing Standards

- Use Pest for all tests
- Place feature tests in `tests/Feature/`
- Place unit tests in `tests/Unit/`
- Test both human and AI modes for commands
- Mock external dependencies with Mockery

## Code Quality Standards

### MANDATORY: Run Before EVERY Commit
**NEVER skip these checks. NO EXCEPTIONS.**

⚠️ **Current Status**: Larastan has some warnings that need fixing (env() usage, json options).
These should be addressed but don't block functionality.

```bash
# Option 1: Use the built-in quality command
php 💩 quality --fix  # Auto-fixes code style and runs all checks

# Option 2: Use composer scripts
composer quality      # Runs pint (fix), phpstan, and tests
composer check        # Runs pint (test only), phpstan, and tests

# Option 3: Run individually
composer pint         # Fix code style
composer pint:test    # Check code style without fixing
composer stan         # Run static analysis
composer test         # Run tests
```

If ANY of these fail, FIX IT before committing. No "I'll fix it later" - fix it NOW.

### Available Quality Scripts
- `composer quality` - Runs all checks with auto-fix
- `composer check` - Runs all checks without auto-fix  
- `php 💩 quality` - Interactive quality command with Laravel Zero task interface
  - Shows progress with loading indicators and checkmarks ✓
  - Works in any PHP project directory (not just THE SHIT)
  - `--fix` - Auto-fix code style issues with Pint
  - `--no-tests` - Skip tests for quick checks
  - `--path=/path/to/project` - Check a different project
  - Gracefully handles missing tools (won't fail if Pint/Stan/Pest not installed)

### Laravel Pint Configuration
- **Standard**: PSR-12 with Laravel preset
- **Auto-fix**: Always run Pint to fix style issues
- **Check only**: Use `./vendor/bin/pint --test` to verify without changing
- **Key Rules**:
  - No unused imports
  - Proper spacing around operators
  - Consistent brace positioning
  - Single quotes for simple strings
  - Trailing commas in multiline arrays

### Larastan (PHPStan) Configuration
- **Level**: 5 (balanced strictness)
- **Config**: `phpstan.neon` in project root
- **Key Checks**:
  - Undefined methods and properties
  - Type mismatches
  - Dead code detection
  - Proper use of Laravel features (no env() outside config)
  - Console command option validation
- **Fix ALL errors**: Don't ignore or suppress unless absolutely necessary

### Testing Standards
- **Framework**: Pest (NOT PHPUnit directly)
- **Structure**: 
  - Feature tests in `tests/Feature/`
  - Unit tests in `tests/Unit/`
- **Coverage**: Aim for >80% on new code
- **Test both modes**: Human interactive AND AI/non-interactive

### Code Style Guidelines

- Follow PSR-12 standards via Laravel Pint
- Use emoji thoughtfully in user-facing output
- Maintain SHIT acronym creativity for components
- Keep commands focused and single-purpose
- Always provide JSON responses in non-interactive mode
- Use Laravel native features over Symfony
- Prefer Laravel Prompts for CLI interactions

## Important Conventions

1. **Emoji Usage**: The 💩 emoji is the brand identity - use it consistently in filenames and output
2. **Error Handling**: Commands should fail gracefully with helpful messages in both human and AI modes
3. **Documentation**: Update `docs/knowledge/` for architectural decisions
4. **Component Isolation**: Each component has its own vendor directory - never share dependencies
5. **Version Constraints**: Support semantic versioning in component requirements

## Commit Standards

- Use clear, concise commit messages with bullet points for multiple changes
- NO Claude Code attribution in commits (no "🤖 Generated with Claude Code" or co-authoring)
- Minimal use of emoji - only 🚀 for major features if absolutely necessary
- Format: `<type>: <description>` (e.g., `fix: resolve OAuth token refresh issue`)
- For multiple changes, use bullet points in the body:
  ```
  feat: add releases endpoint to GitHub client
  
  • Add ReleasesResource class
  • Implement Index, Get, and Latest requests
  • Create ReleaseData DTOs
  • Wire up releases() method in connector
  ```
- Keep commits atomic and focused on a single concern
- Never include sensitive information or API keys