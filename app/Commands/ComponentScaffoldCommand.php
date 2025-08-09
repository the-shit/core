<?php

namespace App\Commands;

use Symfony\Component\Process\Process;

class ComponentScaffoldCommand extends ConduitCommand
{
    protected $signature = 'component:scaffold {name : Component name (e.g., spotify, github, docker)}';
    protected $description = 'Scaffold a new component using the conduit-component skeleton';

    protected function executeCommand(): int
    {
        $name = $this->argument('name');
        $componentsDir = base_path('💩-components');
        $componentPath = $componentsDir . '/' . $name;
        $skeletonPath = '/Users/jordanpartridge/packages/conduit-component';

        // Check if component already exists
        if (is_dir($componentPath)) {
            $this->forceOutput("❌ Component '{$name}' already exists at: {$componentPath}", 'error');
            return self::FAILURE;
        }

        // Create components directory if it doesn't exist
        if (!is_dir($componentsDir)) {
            mkdir($componentsDir, 0755, true);
        }

        // Copy skeleton to component directory
        $this->smartInfo("📦 Creating component '{$name}' from skeleton...");
        
        if (!$this->copyDirectory($skeletonPath, $componentPath)) {
            $this->forceOutput("❌ Failed to copy skeleton directory", 'error');
            return self::FAILURE;
        }

        // Get component details
        $vendor = $this->smartText('Vendor name', '', 'jordanpartridge', true);
        $description = $this->smartText('Component description', '', "Conduit {$name} integration", true);
        $authorName = $this->smartText('Author name', '', 'Jordan Partridge');
        $authorEmail = $this->smartText('Author email', '', 'jordan@partridge.rocks');
        
        // Generate namespace
        $namespace = 'ConduitComponents\\' . ucfirst($name);
        
        // Replace placeholders in files
        $this->smartInfo("🔧 Customizing component files...");
        
        $replacements = [
            '{{VENDOR}}' => $vendor,
            '{{PACKAGE_NAME}}' => "conduit-{$name}",
            '{{DESCRIPTION}}' => $description,
            '{{KEYWORDS}}' => $name,
            '{{AUTHOR_NAME}}' => $authorName,
            '{{AUTHOR_EMAIL}}' => $authorEmail,
            '{{NAMESPACE}}' => $namespace,
        ];

        $this->replaceInDirectory($componentPath, $replacements);

        // Create basic 💩.json manifest
        $this->createManifest($componentPath, $name, $description);

        // Install component dependencies
        $this->smartInfo("📚 Installing component dependencies...");
        $this->installDependencies($componentPath);

        // Create bin directory and executable
        $this->createExecutable($componentPath, $name, $namespace);

        $this->smartInfo("✅ Component '{$name}' scaffolded successfully!");
        $this->smartNewLine();
        $this->smartInfo("📁 Location: {$componentPath}");
        $this->smartInfo("🚀 Next steps:");
        $this->smartLine("   1. Add your business logic to src/{$name}Service.php");
        $this->smartLine("   2. Define commands in src/Commands/");
        $this->smartLine("   3. Test with: php {$componentPath}/component <command>");
        $this->smartLine("   4. Conduit will automatically discover the component");

        return self::SUCCESS;
    }

    private function copyDirectory(string $source, string $destination): bool
    {
        if (!is_dir($source)) {
            return false;
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item->getRealPath(), $target);
            }
        }

        return true;
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
        
        // Skip binary files and vendor directories
        foreach ($excludeDirs as $dir) {
            if (str_contains($filename, DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR)) {
                return false;
            }
        }

        return in_array($extension, ['php', 'json', 'md', 'txt', 'yml', 'yaml', 'stub', '']);
    }

    private function createManifest(string $componentPath, string $name, string $description): void
    {
        $manifest = [
            'name' => ucfirst($name),
            'description' => $description,
            'version' => '1.0.0',
            'executable' => $name,
            'commands' => [
                "{$name}:example" => "Example {$name} command",
                "{$name}:test" => "Test {$name} functionality"
            ]
        ];

        file_put_contents(
            $componentPath . '/💩.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function installDependencies(string $componentPath): void
    {
        $process = new Process(['composer', 'install', '--no-dev'], $componentPath);
        $process->setTimeout(300);
        
        try {
            $process->run();
        } catch (\Exception $e) {
            $this->forceOutput("⚠️  Warning: Failed to install dependencies: " . $e->getMessage(), 'warn');
        }
    }

    private function createExecutable(string $componentPath, string $name, string $namespace): void
    {
        $binDir = $componentPath . '/bin';
        if (!is_dir($binDir)) {
            mkdir($binDir, 0755, true);
        }

        $executablePath = $binDir . '/' . $name;
        $executableContent = $this->generateExecutable($name, $namespace);
        
        file_put_contents($executablePath, $executableContent);
        chmod($executablePath, 0755);
    }

    private function generateExecutable(string $name, string $namespace): string
    {
        $serviceClass = str_replace('\\', '\\\\', $namespace . '\\' . ucfirst($name) . 'Service');
        
        return <<<PHP
#!/usr/bin/env php
<?php

// Component executable for {$name}
require_once __DIR__ . '/../vendor/autoload.php';

use {$serviceClass};

// Parse command line arguments
\$method = \$argv[1] ?? 'help';
\$args = array_slice(\$argv, 2);

// Parse options (--key=value format)  
\$options = [];
\$positionalArgs = [];

foreach (\$args as \$arg) {
    if (str_starts_with(\$arg, '--')) {
        [\$key, \$value] = explode('=', substr(\$arg, 2), 2) + [null, true];
        \$options[\$key] = \$value;
    } else {
        \$positionalArgs[] = \$arg;
    }
}

// Create service instance
\$service = new {$serviceClass}();

try {
    // Handle basic methods
    \$result = match (\$method) {
        'example' => \$service->example(\$positionalArgs[0] ?? 'world'),
        'test' => \$service->test(),
        'help' => \$service->help(),
        default => ['error' => "Unknown method: {\$method}. Try: example, test, help"]
    };

    // Output result
    if (is_string(\$result)) {
        echo \$result . PHP_EOL;
    } elseif (is_array(\$result)) {
        if (isset(\$result['error'])) {
            fwrite(STDERR, "❌ {\$result['error']}" . PHP_EOL);
            exit(1);
        }
        echo json_encode(\$result, JSON_PRETTY_PRINT) . PHP_EOL;
    }

    exit(0);

} catch (Exception \$e) {
    fwrite(STDERR, "❌ Error: " . \$e->getMessage() . PHP_EOL);
    exit(1);
}
PHP;
    }
}