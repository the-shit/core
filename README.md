# 💩 THE SHIT

> **S**caling **H**umans **I**nto **T**omorrow

A Laravel Zero CLI framework built for Human-AI collaboration, featuring a powerful component-based architecture.

## Installation

### Via Composer (Recommended)
```bash
composer global require the-shit/core
```

### From Source
```bash
git clone https://github.com/the-shit/core.git
cd core
composer install
ln -s $(pwd)/💩 /usr/local/bin/💩
```

## Quick Start

```bash
# List all commands
💩 list

# Install a component
💩 install spotify

# List all Laravel sites (with site-manager component)
💩 sites:list

# Check AI status
💩 ai:status
```

## Component System

THE SHIT uses a modular component architecture. Components are discovered from three locations:

1. **Local** (`./💩-components/`) - For development
2. **User** (`~/.shit/components/`) - For user-installed components
3. **System** (`/usr/local/share/shit/components/`) - For system-wide components

### Installing Components

```bash
# Install locally (default)
💩 install spotify

# Install globally for user
💩 install spotify --global

# Install from specific branch
💩 install spotify --branch develop
```

### Available Components

- **ai** - Multi-provider AI integration (OpenAI, Anthropic, xAI)
- **spotify** - Control Spotify playback from the terminal
- **orchestrator** - Manage multiple Claude Code instances
- **obsidian-bridge** - Sync with Obsidian knowledge management
- **site-manager** - Manage Laravel projects with Herd integration
- **focus** - Productivity tracking with music awareness

### Creating Components

Components are standalone Laravel Zero applications that integrate seamlessly with THE SHIT.

```bash
# Scaffold a new component
💩 component:scaffold my-component

# Or scaffold globally
💩 component:scaffold my-component --global
```

Each component needs:
- `💩.json` - Component manifest
- `composer.json` - Dependencies
- `app/Commands/` - Command classes

## Configuration

Configure paths and defaults via environment variables:

```bash
# .env
SHIT_DEFAULT_INSTALL=user
SHIT_USER_COMPONENTS=/custom/path/components
SHIT_SITES_PATH=/custom/sites/path
```

## Human-AI Collaboration

THE SHIT automatically detects interaction mode:

- **Human Mode** - Interactive prompts, formatted output
- **AI Mode** - JSON responses, non-interactive
- **CI Mode** - Automated pipelines

Set your user agent:
```bash
export CONDUIT_USER_AGENT=claude
```

## Event System

Components communicate through an event bus:

```bash
# Emit an event
💩 event:emit "component.action" --data='{"key":"value"}'

# List recent events
💩 event:list
```

## Development

```bash
# Run tests
./vendor/bin/pest

# Code quality checks
composer check

# Fix code style
composer pint
```

## Requirements

- PHP 8.2+
- Composer
- macOS/Linux (Windows via WSL)

## License

MIT

## Credits

Built with [Laravel Zero](https://laravel-zero.com) by Jordan Partridge and the Human-AI collective.

---

*THE SHIT - Because great tools should be fun to use.* 💩