# THE SHIT Rebranding - Conduit v3 Evolution

**Tags**: `shit`, `rebranding`, `v3`, `architecture`, `decision`  
**Type**: `architectural-decision`  
**Component**: `conduit-core`  
**Priority**: `high`  
**Status**: `completed`  
**Date**: 2025-08-08

## Summary

Conduit v3 has been completely rebranded to **THE SHIT** (Scaling Humans Into Tomorrow). This represents a major architectural and philosophical shift that maintains all Conduit functionality while embracing a more playful, memorable identity.

## Key Changes

### 1. Executable Renaming
- **Old**: `conduit`
- **New**: `💩`
- **Impact**: All command invocations now use the emoji executable

### 2. Directory Structure
- **Components Directory**: `conduit-components/` → `💩-components/`
- **Manifest Files**: `conduit.json` → `💩.json`
- **Config Reference**: `config/app.php` name property is now `💩`

### 3. Package Namespace
- **Vendor**: `jordanpartridge/` → `the-shit/`
- **Core Package**: `the-shit/core`
- **GitHub Organization**: `S-H-I-T`

### 4. Component Architecture
- **ComponentServiceProvider**: Updated to scan for `💩.json` manifests
- **ComponentScaffoldCommand**: Creates `💩.json` instead of `conduit.json`
- **Component Namespace**: All use `the-shit/` vendor prefix

## Philosophy

### Primary Meaning
**THE SHIT** = **Scaling Humans Into Tomorrow**

### Release Strategy
- Each release can have different SHIT edition names
- Must include "Tight" in the edition name
- Example: "Super Helpful Integration Toolkit"

### Component Naming
Each component gets its own SHIT acronym:
- Spotify: "Spotify's Here, It's Tight"
- GitHub: "Git Hub Integration Tight"
- Composer: "Composer's Harmoniously Integrating Tight"

## Implementation Details

### Service Provider Changes
```php
// ComponentServiceProvider now scans for 💩.json
$manifestPath = $componentPath . '/💩.json';
```

### Scaffold Command Updates
```php
// Creates 💩.json manifest instead of conduit.json
$manifestContent = [
    'name' => $componentName,
    'version' => '1.0.0',
    // ...
];
```

### Composer Configuration
```json
{
    "name": "the-shit/core",
    "description": "THE SHIT - Scaling Humans Into Tomorrow"
}
```

## Migration Path

1. Update all scripts referencing `conduit` to use `💩`
2. Rename component manifests from `conduit.json` to `💩.json`
3. Update composer dependencies to use `the-shit/` vendor
4. Update GitHub repositories to new organization

## Related Knowledge

- Component Architecture Pattern
- Laravel Zero CLI Framework
- Service Provider Registration
- Component Discovery System

## Impact

This rebranding affects:
- All command invocations
- Component development workflow
- Package distribution
- Documentation and examples
- Developer onboarding

## Rationale

The rebranding serves multiple purposes:
1. **Memorable**: The emoji and acronym are unforgettable
2. **Playful**: Reduces formality, increases approachability
3. **Flexible**: SHIT acronyms allow creative naming
4. **Distinctive**: Stands out in the CLI tool ecosystem

## Future Considerations

- Component marketplace will use SHIT branding
- Documentation site: theshit.dev
- Component certification: "Certified SHIT"
- Community: "SHIT Contributors"

---

**Note**: This is a completed architectural decision. All core functionality has been migrated to the new branding.