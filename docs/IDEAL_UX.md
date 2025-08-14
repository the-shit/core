# THE SHIT - Ideal UX Design

## Command Philosophy
- Type `shit`, see `💩` in outputs
- Natural language first, commands second
- Progressive disclosure (simple → advanced)
- Context-aware suggestions

## Primary Interface

```bash
# BRAIN MODE (default - no subcommand needed)
shit "fix the failing test"           # → Finds test, runs it, shows error, fixes it
shit "what's broken"                  # → Runs tests, linters, checks git status
shit "deploy this"                    # → Checks branch, runs tests, creates PR
shit "play focus music"               # → Starts focus timer + Spotify playlist
shit "commit these changes"           # → Smart commit with generated message
shit "what can you do"                # → Shows contextual commands based on pwd

# DIRECT COMMANDS (when you know what you want)
shit test                            # Run tests (auto-detects framework)
shit fix                             # Fix all issues (tests, lint, types)
shit commit                          # Stage all, generate message, commit
shit pr                              # Create PR with AI-generated description
shit focus                           # Start 25min session + music
shit status                          # Everything status (git, tests, AI, focus)
```

## Command Hierarchy

### 🧠 Core Commands (always visible)
```bash
shit                                 # Brain mode (default)
shit help                           # Smart help (context-aware)
shit fix                            # Fix everything broken
shit test                           # Run tests
shit commit                         # Smart commit
shit status                         # Universal status
```

### 🔥 Quick Actions (one word, memorable)
```bash
shit init                           # Setup project (detects framework)
shit clean                          # Clean everything (cache, deps, build)
shit update                         # Update all deps + components
shit sync                           # Sync with remote (pull, install, migrate)
shit build                          # Build project
shit deploy                         # Deploy (detects platform)
shit watch                          # Watch mode for current task
```

### 🎯 Contextual Commands (shown when relevant)
```bash
# In git repo:
shit pr                             # Create pull request
shit conflicts                      # Resolve merge conflicts
shit blame <file>                   # Enhanced git blame with AI context

# Laravel project:
shit migrate                        # Run migrations
shit tinker                         # Enhanced tinker with AI
shit make model User                # Scaffolding

# Has components:
shit install <component>            # Install from ecosystem
shit components                     # List/manage components

# Spotify connected:
shit play                           # Resume/play music
shit focus                          # Focus mode + music
shit vibe <mood>                    # Play mood-based playlist
```

### 🚀 Power User Commands (hidden by default, show with --power)
```bash
shit orchestrate                    # Multi-Claude coordination
shit brain train                    # Train brain on your patterns
shit macro record                   # Record command sequence
shit alias <name> <command>         # Create custom aliases
shit workflow <name>                # Run saved workflow
```

## Smart Features

### 1. Brain Mode (Default)
```bash
$ shit
💩 What do you need?
> fix the test that's failing

🧠 Found 1 failing test: UserAuthTest::test_login
   ❌ Expected 200, got 401

🔧 Issue: Missing CSRF token in test request
   Fixed in: tests/Feature/UserAuthTest.php:34

✅ All tests passing now!
```

### 2. Progressive Disclosure
```bash
$ shit help
💩 THE SHIT - Common Commands

  shit "natural language"    Ask me anything
  shit test                 Run tests
  shit fix                  Fix issues
  shit commit               Smart commit
  shit focus                Start focus session

  More: shit help --all
  Power: shit help --power
```

### 3. Context Awareness
```bash
$ shit status
💩 Project Status

📁 Laravel project (the-shit)
🌿 Branch: feature/brain-mode (3 commits ahead)
✅ Tests: 42 passing
🔧 Lint: 2 warnings
🤖 AI: GPT-5 (500 tokens left today)
🎯 Focus: 2h 15m today (3 sessions)
```

### 4. Intelligent Routing
```bash
# Natural language → Best action
"run tests"          → shit test
"what's failing"     → shit test --failed
"fix lint"          → shit fix --lint
"show changes"      → git diff (pretty)
"cleanup"           → shit clean
"start working"     → shit focus
"take a break"      → shit break
```

### 5. Fuzzy Matching & Autocorrect
```bash
$ shit tset
💩 Did you mean 'test'? (y/n) y
Running tests...

$ shit com
💩 Multiple matches:
  1. commit - Smart commit changes
  2. components - Manage components
  3. compose - Docker compose helper
Which? [1]:
```

## Configuration

### ~/.config/shit/config.yml
```yaml
# Defaults
brain:
  provider: openai
  model: gpt-5
  personality: helpful  # or: sarcastic, professional, friendly

focus:
  duration: 25
  music: true
  playlist: "Deep Focus"

commands:
  aliases:
    c: commit
    t: test
    f: fix
  
workflows:
  morning:
    - sync
    - test
    - status
  
  ship:
    - test
    - fix
    - commit
    - pr
```

## Installation

```bash
# One command install
curl -fsSL https://shit.dev/install | bash

# Creates:
# - /usr/local/bin/shit (executable)
# - ~/.config/shit/ (config)
# - Adds to PATH
# - Sets up completions

# First run
$ shit
💩 Welcome to THE SHIT! Let's set you up...
🤖 Choose AI provider: [OpenAI]/Anthropic/Local
🎵 Connect Spotify? [Y/n]
⚡ Enable power mode? [y/N]

✅ You're all set! Try: shit "help me understand this codebase"
```

## Key Improvements Over Current

1. **Executable `shit`** - No `php` prefix, instant muscle memory
2. **Brain mode by default** - Just type `shit "do something"`
3. **Command hierarchy** - Core → Quick → Contextual → Power
4. **Actual intelligence** - Brain mode that works
5. **Speed** - Rust binary, not PHP, <50ms response
6. **Fuzzy everything** - Commands, files, components
7. **Workflows** - Combine commands into reusable sequences
8. **Universal status** - One command for everything status

## Output Examples

```bash
$ shit
💩 What can I help with?
> my tests are failing

🧠 Running test suite...

❌ 3 tests failing:
  • AuthTest::test_login - Missing auth token
  • AuthTest::test_logout - Route not found  
  • UserTest::test_create - Validation error

🔧 Want me to fix these? (Y/n) y

✨ Fixed all 3 issues:
  • Added auth token to test request
  • Registered logout route
  • Fixed validation rules

✅ All 45 tests passing!
```

## The Magic Moment

```bash
# Developer's first experience
$ shit
💩 Welcome! I see you're in a Laravel project. 

   I found:
   • 12 tests (all passing ✅)
   • 3 pending migrations
   • 2 linting warnings
   • No git commits today

   Try: shit "fix those warnings" or just shit fix

> fix everything and commit

🔧 Fixing 2 linting issues...
📦 Running migrations...
✅ All clean!

📝 Commit message:
   "Fix linting issues and run pending migrations
   
   - Fixed undefined variable in UserController
   - Fixed missing return type in ApiHelper
   - Ran 3 pending migrations for user preferences"

Commit? (Y/n) y

✨ Done! Ready to ship.
```

This is THE SHIT that would make developers actually want to use it daily.