# Component Installation System

**Tags**: `components`, `install`, `github`, `shit`, `pattern`, `feature`  
**Type**: `feature`  
**Component**: `the-shit-core`  
**Architecture**: `command-pattern`, `github-integration`  
**Priority**: `high`  
**Date**: 2025-08-08

## Overview

Created `ComponentInstallCommand` that installs components directly from GitHub, similar to Composer but without Packagist dependency. This enables THE SHIT to have its own component ecosystem without relying on external package registries.

## Key Features

1. **Command**: `💩 install <component> [version]`
2. **Source**: Pulls from GitHub organization S-H-I-T
3. **Version Support**: Supports version constraints (^1.0, ~2.1, exact versions)
4. **Fallback**: Falls back to main branch if no releases exist
5. **Automation**: Automatically runs composer install in component directory
6. **Permissions**: Makes executables chmod +x
7. **Cleanup**: Removes .git directory to keep components clean

## Implementation Details

### Architecture
- Uses native PHP `file_get_contents` for GitHub API (no HTTP client dependency)
- Clones with `--depth 1` for efficiency
- Reads `💩.json` manifest for executable configuration
- Components are isolated with their own vendor directories

### Version Resolution
- Supports Composer-style version constraints
- Fetches releases from GitHub API
- Intelligently selects best matching version
- Falls back to main branch when no releases exist

### Installation Process
1. Resolves version constraint to specific tag/branch
2. Clones repository with minimal depth
3. Removes .git directory
4. Runs composer install in component directory
5. Sets executable permissions based on manifest
6. Registers component in local registry

## Usage Examples

```bash
# Install latest version
💩 install spotify

# Install specific major version
💩 install github ^2.0

# Install with tilde constraint
💩 install docker ~1.5

# Install exact version
💩 install composer 1.2.3
```

## Benefits

1. **Self-contained**: No dependency on Packagist or other registries
2. **GitHub-native**: Leverages GitHub's release system
3. **Composer-compatible**: Uses familiar version constraints
4. **Isolated**: Each component has its own dependencies
5. **Clean**: Removes git history to reduce disk usage

## Related Knowledge

- Component scaffold system for creating new components
- Component registry for tracking installed components
- GitHub organization structure at S-H-I-T

## Future Enhancements

- Support for private repositories with authentication
- Component update command to upgrade versions
- Dependency resolution between components
- Local component development linking

---

**Note**: This installation system embodies THE SHIT philosophy - keeping things tight, focused, and developer-friendly while maintaining serious functionality.