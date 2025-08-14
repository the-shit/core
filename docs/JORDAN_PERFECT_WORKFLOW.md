# THE SHIT - Jordan's Perfect Daily Workflow

## Morning Routine
```bash
# Start the day
💩 morning
# → Starts focus timer (25min)
# → Plays morning focus playlist
# → Shows git status across all repos
# → Lists today's PRs to review
# → Shows overnight CI failures
# → Displays Conduit component updates

# Quick status check
💩 status
# → Active Claude instances (orchestration status)
# → Current branch + uncommitted changes
# → Running tests/builds
# → Focus time today
# → Active Spotify track
```

## Core Development Flow (Conduit/Laravel)

### Working with Claude Orchestration
```bash
# Start coding session with multiple Claudes
💩 orchestrate "implement new Docker component"
# → Spins up 3 Claude instances
# → Assigns roles (architect, implementer, tester)
# → Prevents file conflicts
# → Tracks progress in real-time

# Check Claude conflicts before committing
💩 claude:conflicts
# → Shows which files are being edited by which Claude
# → Prevents commit disasters

# Hand off work between sessions
💩 claude:handoff "continue the Docker implementation"
# → Captures current state
# → Generates context for next Claude
# → Preserves knowledge
```

### Conduit Component Development
```bash
# Quick component scaffold
💩 component:new github-actions
# → Scaffolds full component structure
# → Sets up GitHub repo
# → Initializes with Jordan's defaults
# → Creates initial tests

# Test component in isolation
💩 component:test github-actions
# → Runs component's test suite
# → Validates manifest
# → Checks integration points

# Release component
💩 component:release github-actions
# → Runs tests
# → Bumps version
# → Creates GitHub release
# → Updates main Conduit registry
```

### Knowledge Management (Critical for AI Context)
```bash
# Capture solution for future Claudes
💩 knowledge:capture "Fixed Laravel Zero command registration by..."
# → Saves to Conduit knowledge base
# → Tags automatically (Laravel, debugging, etc.)
# → Available to all future Claude sessions

# Search knowledge before debugging
💩 knowledge:search "command not found"
# → Searches all captured knowledge
# → Shows relevant solutions
# → Includes context from when it was solved
```

### Smart Git Workflow
```bash
# AI-powered commits (but YOUR style)
💩 commit
# → Analyzes changes
# → Generates commit message in Jordan's style
# → NO Claude attribution (as per your rules)
# → Follows conventional commits

# Quick PR creation
💩 pr
# → Creates PR with generated description
# → Links to relevant issues
# → Adds reviewers based on files changed
# → Posts to Slack/Discord

# Daily standup helper
💩 standup
# → Yesterday's commits
# → Today's planned work (from brain)
# → Any blockers
# → Formats for Slack
```

## Focus & Productivity

```bash
# Deep work mode
💩 focus:deep
# → 90min timer (not 25)
# → Blocks Slack/Discord
# → Sets Spotify to instrumental
# → Sets commit message prefix to "🎯 focused:"

# Context switching
💩 context:switch pstrax
# → Stashes current changes
# → Switches to Pstrax repos
# → Loads Pstrax-specific env vars
# → Shows Pstrax PR reviews needed

💩 context:switch conduit
# → Back to Conduit work
# → Restores stashed changes
# → Loads Conduit env
```

## AI Brain That Actually Works

```bash
# Natural language that understands YOUR context
💩 brain "fix the delegation issue"
# → Knows you mean ComponentServiceProvider
# → Searches your knowledge base first
# → Checks recent commits for context
# → Actually fixes it or shows how

💩 brain "ship the Docker component"
# → Runs tests
# → Fixes any issues
# → Commits with your style
# → Creates PR
# → Updates component registry

💩 brain "what was I working on yesterday"
# → Checks git commits
# → Reviews Claude orchestration logs
# → Shows focus sessions
# → Summarizes in bullet points
```

## End of Day

```bash
# Wrap up
💩 eod
# → Commits any uncommitted work to WIP branch
# → Logs work to Conduit journal
# → Shows tomorrow's calendar
# → Kills any running Claude instances
# → Posts summary to team Slack

# Weekend mode
💩 weekend
# → Archives week's work
# → Generates weekly report
# → Sets up fresh workspace for Monday
# → Suggests weekend project ideas
```

## Jordan-Specific Power Commands

```bash
# Laravel Zero specific helpers
💩 zero:fix
# → Fixes common Laravel Zero issues
# → Clears compiled cache
# → Rebuilds command cache
# → Reregisters service providers

# Component architecture helpers
💩 arch:validate <component>
# → Checks against liberation philosophy
# → Validates standalone operation
# → Ensures proper delegation patterns

# Documentation that writes itself
💩 docs:component <name>
# → Generates docs from code
# → Includes usage examples from tests
# → Formats for Conduit standards

# Pstrax-specific
💩 pstrax:deploy
# → Runs Pstrax-specific deploy pipeline
# → Posts to Pstrax Slack
# → Updates Pstrax documentation
```

## The Hidden Gems

```bash
# When shit hits the fan
💩 unfuck
# → Kills all processes
# → Clears all caches  
# → Resets git to last known good state
# → Restarts everything clean

# The "I'm stuck" button
💩 help-me
# → Analyzes current error
# → Searches Stack Overflow
# → Checks your knowledge base
# → Asks GPT-5 if needed
# → Shows solution

# Friday afternoon special
💩 yolo
# → Commits everything
# → Force pushes to main
# → Deploys to production
# → Orders pizza
# (Just kidding... or am I?)
```

## Configuration: `.shit/jordan.yml`

```yaml
defaults:
  ai_provider: openai
  ai_model: gpt-5
  focus_duration: 90  # Jordan works in 90min blocks
  music:
    focus: "Deep Focus Instrumental"
    debug: "Liquid DnB"
    friday: "Classic Rock"

projects:
  conduit:
    path: ~/Code/conduit
    default_branch: main
    test_command: "./vendor/bin/pest"
  
  pstrax:
    path: ~/Code/pstrax
    default_branch: develop
    test_command: "npm test"

orchestration:
  max_claudes: 5
  conflict_check: true
  auto_handoff: true

aliases:
  c: commit
  p: pr
  f: focus:deep
  o: orchestrate
  k: knowledge:capture
  b: brain

workflows:
  morning:
    - status
    - focus:deep
    - spotify:play "Morning Focus"
  
  ship:
    - test
    - commit
    - pr
    - knowledge:capture "Shipped: {branch_name}"
```

## Why This Would Actually Work for Jordan

1. **Orchestration-First**: Built around managing multiple Claude instances
2. **Knowledge Capture**: Every solution saved for future sessions
3. **Context Aware**: Knows about Conduit, Pstrax, Laravel Zero
4. **Focus on Flow**: 90min blocks, not pomodoro
5. **Real Integration**: Spotify + Git + Claude + Slack
6. **Jordan's Style**: Commits without Claude attribution
7. **Liberation Philosophy**: Components stay standalone

This isn't a generic tool - it's Jordan's personal command center.