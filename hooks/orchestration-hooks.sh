#!/usr/bin/env bash

# 🎭 THE SHIT Orchestration Hooks for Claude Code
# These hooks integrate with Claude Code's settings to provide real-time tracking

# Get THE SHIT path
SHIT_PATH="${SHIT_PATH:-$(dirname "$(dirname "$(realpath "$0")")")}"
SHIT_CLI="${SHIT_PATH}/💩"

# Instance ID from environment or generate
INSTANCE_ID="${CLAUDE_INSTANCE_ID:-claude_unknown_$$}"

# Hook: Before editing a file
orchestrate_before_edit() {
    local file="$1"
    
    if [ -z "$file" ]; then
        return 0
    fi
    
    # Check with orchestrator
    result=$(php "$SHIT_CLI" orchestrate check \
        --instance="$INSTANCE_ID" \
        --check-file="$file" \
        --action-type="edit" \
        --no-interaction 2>/dev/null)
    
    if echo "$result" | grep -q '"available":false'; then
        locked_by=$(echo "$result" | grep -oP '"locked_by":"\K[^"]+')
        echo "⚠️  CONFLICT: File is being edited by $locked_by" >&2
        return 1
    fi
    
    return 0
}

# Hook: After editing a file
orchestrate_after_edit() {
    local file="$1"
    
    if [ -z "$file" ]; then
        return 0
    fi
    
    # Update instance with file
    php "$SHIT_CLI" orchestrate update \
        --instance="$INSTANCE_ID" \
        --files="$file" \
        --heartbeat \
        --no-interaction > /dev/null 2>&1
}

# Hook: Before creating a file
orchestrate_before_create() {
    local file="$1"
    
    if [ -z "$file" ]; then
        return 0
    fi
    
    # Check with orchestrator
    php "$SHIT_CLI" orchestrate check \
        --instance="$INSTANCE_ID" \
        --check-file="$file" \
        --action-type="create" \
        --no-interaction > /dev/null 2>&1
}

# Hook: On task change
orchestrate_task_change() {
    local task="$1"
    
    if [ -z "$task" ]; then
        return 0
    fi
    
    # Update instance task
    php "$SHIT_CLI" orchestrate update \
        --instance="$INSTANCE_ID" \
        --task="$task" \
        --no-interaction > /dev/null 2>&1
}

# Hook: Heartbeat
orchestrate_heartbeat() {
    php "$SHIT_CLI" orchestrate update \
        --instance="$INSTANCE_ID" \
        --heartbeat \
        --no-interaction > /dev/null 2>&1
}

# Export all functions
export -f orchestrate_before_edit
export -f orchestrate_after_edit
export -f orchestrate_before_create
export -f orchestrate_task_change
export -f orchestrate_heartbeat