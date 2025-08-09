# THE SHIT Quick Reference

**Tags**: `shit`, `reference`, `development`, `pattern`  
**Type**: `pattern`  
**Priority**: `medium`  
**Date**: 2025-08-08

## Command Usage

```bash
# Old way (deprecated)
php conduit components list

# New way
php 💩 components list
```

## Component Development

### Creating a Component
```bash
php 💩 component:scaffold my-component
```

### Component Manifest (`💩.json`)
```json
{
    "name": "my-component",
    "description": "My Component's Here, It's Tight",
    "version": "1.0.0",
    "authors": [{
        "name": "Your Name",
        "email": "you@example.com"
    }],
    "commands": {
        "my-component:example": "App\\Commands\\ExampleCommand"
    }
}
```

### Directory Structure
```
💩-components/
├── my-component/
│   ├── 💩.json
│   ├── composer.json
│   ├── app/
│   │   └── Commands/
│   └── config/
```

## Package References

### Composer Require
```bash
# Core package
composer require the-shit/core

# Component packages
composer require the-shit/spotify-component
composer require the-shit/github-component
```

### Namespace Usage
```php
namespace TheShit\MyComponent\Commands;

use TheShit\Core\Command;
```

## Common SHIT Acronyms

- **Core**: Scaling Humans Into Tomorrow
- **Spotify**: Spotify's Here, It's Tight
- **GitHub**: Git Hub Integration Tight
- **Composer**: Composer's Harmoniously Integrating Tight
- **Laravel**: Laravel's Artisan Really Integrates Tight

## Development Workflow

1. **Clone**: `git clone git@github.com:S-H-I-T/core.git`
2. **Install**: `composer install`
3. **Run**: `php 💩 [command]`
4. **Build**: `php 💩 app:build`

## Tips

- Always use the 💩 emoji for the executable
- Component manifests must be named `💩.json`
- Each component should have a creative SHIT acronym
- Maintain the playful spirit while building serious tools

---

**Remember**: It's THE SHIT, and that's a good thing!