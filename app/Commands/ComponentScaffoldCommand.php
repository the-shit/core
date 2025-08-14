<?php

namespace App\Commands;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\suggest;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class ComponentScaffoldCommand extends ConduitCommand
{
    protected $signature = 'component:scaffold {name? : Component name (e.g., spotify, github, docker)} {--template=default : Template to use} {--global : Scaffold in ~/.shit/components} {--json : Output as JSON}';

    protected $description = '🎨 Create a beautiful new component from the official skeleton';

    protected function executeCommand(): int
    {
        // Beautiful intro
        if (!$this->option('json')) {
            intro('🎨 THE SHIT Component Scaffolder');
            note('Let\'s create something amazing together!');
        }

        // Get component name with validation
        $name = $this->argument('name') ?? suggest(
            label: 'What should we call your component?',
            options: fn ($value) => $this->suggestComponentNames($value),
            placeholder: 'e.g., spotify, github, docker',
            validate: fn ($value) => $this->validateComponentName($value)
        );

        // Determine scaffold directory based on --global flag or config
        if ($this->option('global')) {
            $componentsDir = config('shit.paths.components.user');
        } else {
            $default = config('shit.defaults.component_scaffold', 'local');
            $componentsDir = config("shit.paths.components.{$default}");
        }
        $componentPath = $componentsDir.'/'.$name;

        // Check if component already exists
        if (is_dir($componentPath)) {
            $this->forceOutput("❌ Component '{$name}' already exists at: {$componentPath}", 'error');
            
            if (!$this->option('json')) {
                if (confirm("Would you like to remove it and start fresh?", false)) {
                    spin(
                        fn () => Process::run("rm -rf {$componentPath}"),
                        'Removing existing component...'
                    );
                } else {
                    return self::FAILURE;
                }
            } else {
                return self::FAILURE;
            }
        }

        // Create components directory if it doesn't exist
        if (!is_dir($componentsDir)) {
            mkdir($componentsDir, 0755, true);
        }

        // Clone the skeleton from GitHub
        $cloned = spin(
            callback: fn () => $this->cloneSkeletonFromGitHub($componentPath),
            message: '📦 Fetching the latest skeleton from GitHub...'
        );

        if (!$cloned) {
            $this->forceOutput('❌ Failed to clone skeleton from GitHub', 'error');
            return self::FAILURE;
        }

        // Get component details with beautiful prompts
        if (!$this->option('json')) {
            info('Tell me about your component:');
        }

        $description = text(
            label: 'Component description',
            placeholder: "e.g., Spotify integration for music control",
            default: "THE SHIT {$name} component",
            required: true
        );

        $shitAcronym = $this->generateShitAcronym($name);
        
        $vendor = text(
            label: 'GitHub username/organization',
            default: 'the-shit',
            required: true
        );

        $authorName = text(
            label: 'Your name',
            default: 'Jordan Partridge'
        );

        $authorEmail = text(
            label: 'Your email',
            default: 'jordan@partridge.rocks'
        );

        // Select component type for better scaffolding
        $componentType = select(
            label: 'What type of component is this?',
            options: [
                'api' => '🌐 API Integration (REST/GraphQL)',
                'cli' => '⚡ CLI Tool (System commands)',
                'service' => '⚙️ Service Integration (Database/Queue)',
                'utility' => '🔧 Utility (Helper functions)',
                'ai' => '🤖 AI/ML Integration',
                'other' => '📦 Other'
            ],
            default: 'other'
        );

        // Customize based on type
        $replacements = [
            '{{COMPONENT_NAME}}' => $name,
            '{{VENDOR}}' => $vendor,
            '{{PACKAGE_NAME}}' => $name,
            '{{DESCRIPTION}}' => $description,
            '{{KEYWORDS}}' => $name,
            '{{AUTHOR_NAME}}' => $authorName,
            '{{AUTHOR_EMAIL}}' => $authorEmail,
            '{{SHIT_ACRONYM}}' => $shitAcronym,
        ];

        // Replace placeholders
        $replaced = spin(
            callback: fn () => $this->replaceInDirectory($componentPath, $replacements),
            message: '🔧 Customizing component files...'
        );

        // Update manifest
        $this->updateManifest($componentPath, $name, $description, $shitAcronym, $componentType);

        // Clean up git directory and reinitialize
        spin(
            callback: function () use ($componentPath, $vendor, $name) {
                Process::path($componentPath)->run('rm -rf .git');
                Process::path($componentPath)->run('git init');
                Process::path($componentPath)->run('git add .');
                Process::path($componentPath)->run("git commit -m 'Initial scaffold of {$name} component'");
                Process::path($componentPath)->run("git remote add origin https://github.com/{$vendor}/{$name}.git 2>/dev/null");
            },
            message: '🔄 Initializing git repository...'
        );

        // Install dependencies
        $installed = spin(
            callback: fn () => $this->installDependencies($componentPath),
            message: '📚 Installing dependencies (this may take a moment)...'
        );

        // Make component executable
        chmod($componentPath.'/component', 0755);

        // Generate initial command based on type
        $this->generateInitialCommand($componentPath, $name, $componentType);

        // Beautiful success message
        if (!$this->option('json')) {
            outro("✨ Component '{$name}' created successfully!");
            
            note("📁 Location: {$componentPath}");
            
            info('🚀 Next steps:');
            $this->line('');
            $this->line("  1. Add your commands:");
            $this->line("     <fg=cyan>cd {$componentPath}</>");
            $this->line("     <fg=cyan>./component make:command YourCommand</>");
            $this->line('');
            $this->line("  2. Update the manifest:");
            $this->line("     <fg=cyan>nano 💩.json</>");
            $this->line('');
            $this->line("  3. Test your component:");
            $this->line("     <fg=cyan>./component list</>");
            $this->line('');
            $this->line("  4. Use in THE SHIT:");
            $this->line("     <fg=cyan>php 💩 {$name}:example</>");
            $this->line('');
            
            if (confirm('Would you like to create your first command now?', true)) {
                $commandName = text('Command name (e.g., "sync", "fetch", "process")');
                Process::path($componentPath)->tty()->run("./component make:command {$commandName}");
                info("Command created! Edit it at: app/Commands/{$commandName}.php");
            }
        }

        return self::SUCCESS;
    }

    private function cloneSkeletonFromGitHub(string $path): bool
    {
        $result = Process::timeout(300)->run([
            'git', 'clone',
            '--depth', '1',
            '--branch', 'master',
            'https://github.com/conduit-ui/conduit-component.git',
            $path
        ]);

        return $result->successful();
    }

    private function suggestComponentNames(string $value): array
    {
        if (strlen($value) < 2) {
            return [];
        }

        return collect([
            'spotify', 'github', 'docker', 'redis', 'slack',
            'discord', 'telegram', 'notion', 'jira', 'stripe',
            'twilio', 'openai', 'anthropic', 'database', 'cache'
        ])
        ->filter(fn ($name) => str_contains($name, strtolower($value)))
        ->take(5)
        ->values()
        ->toArray();
    }

    private function validateComponentName(?string $value): ?string
    {
        if (empty($value)) {
            return 'Component name is required';
        }

        if (!preg_match('/^[a-z][a-z0-9-]*$/', $value)) {
            return 'Component name must be lowercase letters, numbers, and hyphens only';
        }

        if (strlen($value) < 2 || strlen($value) > 50) {
            return 'Component name must be between 2 and 50 characters';
        }

        return null;
    }

    private function generateShitAcronym(string $name): string
    {
        $words = explode('-', $name);
        $acronyms = [
            'Scalable', 'Smart', 'Super', 'Sophisticated', 'Streamlined',
            'Harmonious', 'Helpful', 'High-performance', 'Hybrid',
            'Intelligent', 'Integrated', 'Innovative', 'Intuitive',
            'Technology', 'Tool', 'Toolkit', 'Terminal'
        ];

        $result = [];
        foreach (str_split(strtoupper($name[0] . 'HIT')) as $i => $letter) {
            $matchingWords = array_filter($acronyms, fn($word) => $word[0] === $letter);
            if (!empty($matchingWords)) {
                $result[] = $matchingWords[array_rand($matchingWords)];
            }
        }

        return implode(' ', $result);
    }

    private function updateManifest(string $componentPath, string $name, string $description, string $acronym, string $type): void
    {
        $manifestPath = $componentPath.'/💩.json';
        
        $manifest = [
            'name' => $name,
            'description' => $description,
            'version' => '1.0.0',
            'shit_acronym' => $acronym,
            'commands' => [
                "{$name}:example" => "Example command for {$name}",
            ],
            'requires' => [
                'php' => '^8.2',
                'laravel-zero/framework' => '^12.0'
            ]
        ];

        // Add type-specific commands
        switch ($type) {
            case 'api':
                $manifest['commands']["{$name}:fetch"] = "Fetch data from {$name}";
                $manifest['commands']["{$name}:sync"] = "Sync with {$name} API";
                break;
            case 'cli':
                $manifest['commands']["{$name}:run"] = "Run {$name} command";
                $manifest['commands']["{$name}:status"] = "Check {$name} status";
                break;
            case 'service':
                $manifest['commands']["{$name}:connect"] = "Connect to {$name}";
                $manifest['commands']["{$name}:health"] = "Check {$name} health";
                break;
            case 'ai':
                $manifest['commands']["{$name}:prompt"] = "Send prompt to {$name}";
                $manifest['commands']["{$name}:models"] = "List available models";
                break;
        }

        file_put_contents(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function generateInitialCommand(string $componentPath, string $name, string $type): void
    {
        // Convert hyphenated names to CamelCase for file name
        $commandName = str_replace('-', '', ucwords($name, '-')) . 'Command';
        $commandPath = $componentPath . '/app/Commands/' . $commandName . '.php';
        
        $template = $this->getCommandTemplate($name, $type);
        
        file_put_contents($commandPath, $template);
    }

    private function getCommandTemplate(string $name, string $type): string
    {
        // Convert hyphenated names to CamelCase for class name
        $className = str_replace('-', '', ucwords($name, '-')) . 'Command';
        $signature = $name . ':example {argument? : Optional argument} {--option= : Optional option}';
        
        return <<<PHP
<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class {$className} extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected \$signature = '{$signature}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected \$description = 'Example command for {$name} component';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        \$this->info('🚀 {$name} component is working!');
        
        if (\$argument = \$this->argument('argument')) {
            \$this->line("Argument received: {\$argument}");
        }
        
        if (\$option = \$this->option('option')) {
            \$this->line("Option received: {\$option}");
        }
        
        // TODO: Add your {$name} logic here
        
        return self::SUCCESS;
    }
}
PHP;
    }

    private function replaceInDirectory(string $directory, array $replacements): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $this->shouldProcessFile($file->getPathname())) {
                $content = file_get_contents($file->getPathname());
                $newContent = str_replace(array_keys($replacements), array_values($replacements), $content);

                if ($content !== $newContent) {
                    file_put_contents($file->getPathname(), $newContent);
                }
            }
        }
    }

    private function shouldProcessFile(string $filename): bool
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $excludeDirs = ['vendor', '.git', 'node_modules'];

        foreach ($excludeDirs as $dir) {
            if (str_contains($filename, DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR)) {
                return false;
            }
        }

        return in_array($extension, ['php', 'json', 'md', 'txt', 'yml', 'yaml', 'xml', 'stub']);
    }

    private function installDependencies(string $componentPath): bool
    {
        $result = Process::path($componentPath)
            ->timeout(300)
            ->run('composer install --no-dev --quiet');
        
        return $result->successful();
    }
}