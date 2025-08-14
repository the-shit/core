# THE SHIT Component Standards

## Required Output Formats

ALL component commands MUST support these standard options:

### 1. `--json` (Required)
Every command must provide JSON output when `--json` is passed:
```php
if ($this->option('json')) {
    $this->line(json_encode(['result' => $data]));
    return;
}
```

### 2. Human-Readable (Default)
Without `--json`, output should be formatted for humans:
- Use tables for structured data
- Use colors and emojis for clarity
- Use Laravel Prompts for interaction

### 3. Exit Codes
- `0` - Success
- `1` - General error
- `2` - Misuse of command

## Command Design Rules

### ✅ DO: Simple, Single-Purpose Commands
```bash
ai:provider openai       # Set provider
ai:model gpt-4o         # Set model
ai:ask "question"       # Ask question
```

### ❌ DON'T: Complex Options
```bash
# This breaks delegation!
ai:ask --provider=openai --model=gpt-4o "question"
```

## State Management

Commands should persist settings between calls:
```php
// Store in storage/component_state.json
$state = ['provider' => 'openai'];
file_put_contents(storage_path('state.json'), json_encode($state));
```

## Human-AI Collaboration

Every command should detect the user agent:
```php
$isAI = in_array($_ENV['CONDUIT_USER_AGENT'] ?? '', ['ai', 'claude']);

if ($isAI || $this->option('json')) {
    // Return structured data
    return $this->jsonResponse($data);
}

// Human-friendly output
$this->table(['Column'], $data);
```

## Example Implementation

```php
class StatusCommand extends Command
{
    protected $signature = 'status {--json : Output as JSON}';
    
    public function handle()
    {
        $data = [
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'status' => 'active'
        ];
        
        if ($this->option('json')) {
            $this->line(json_encode($data));
            return self::SUCCESS;
        }
        
        // Human output
        $this->info('🤖 AI Status');
        $this->table(
            ['Setting', 'Value'],
            collect($data)->map(fn($v, $k) => [$k, $v])->values()
        );
        
        return self::SUCCESS;
    }
}
```

## Benefits

1. **Delegation works** - No custom options to break validation
2. **Composable** - Commands can be chained
3. **Predictable** - Same patterns everywhere
4. **AI-Ready** - JSON output for automation
5. **Human-Friendly** - Beautiful CLI output by default