<?php

namespace App\Commands;

class ComponentConfigCommand extends ConduitCommand
{
    protected $signature = 'component:config {--json : Output as JSON}';

    protected $description = 'Configure component scaffolding preferences (GitHub username, PHP namespace, etc.)';

    protected function executeCommand(): int
    {
        $this->smartInfo('🔧 Component Scaffolding Configuration');
        $this->smartNewLine();

        // Intelligent defaults
        $defaultGithubUsername = $this->detectGithubUsername();
        $defaultPhpNamespace = $this->detectPhpNamespace($defaultGithubUsername);
        $defaultAuthorEmail = $this->detectAuthorEmail();

        // Smart prompts that work for both humans and AI
        $githubUsername = $this->smartText(
            label: 'GitHub Username',
            placeholder: 'e.g., jordanpartridge',
            default: $defaultGithubUsername,
            required: true,
            hint: 'Your GitHub username (lowercase, for package names)'
        );

        $phpNamespace = $this->smartText(
            label: 'PHP Namespace',
            placeholder: 'e.g., JordanPartridge',
            default: $defaultPhpNamespace,
            required: true,
            hint: 'Your preferred PHP namespace prefix (PascalCase)'
        );

        $authorEmail = $this->smartText(
            label: 'Author Email',
            placeholder: 'e.g., jordan@example.com',
            default: $defaultAuthorEmail,
            required: true,
            hint: 'Email for composer.json author field'
        );

        // Show preview (only in human mode)
        $this->smartNewLine();
        $this->smartInfo('📋 Configuration Preview:');
        $this->smartLine("   GitHub Username: {$githubUsername}");
        $this->smartLine("   PHP Namespace: {$phpNamespace}");
        $this->smartLine("   Author Email: {$authorEmail}");
        $this->smartNewLine();

        $exampleComponent = 'docker';
        $this->smartLine('📦 Example for component "'.$exampleComponent.'":');
        $this->smartLine("   Package: {$githubUsername}/conduit-{$exampleComponent}");
        $this->smartLine("   Namespace: {$phpNamespace}\\Conduit".ucfirst($exampleComponent));
        $this->smartNewLine();

        // Smart confirm
        if (! $this->smartConfirm('Save these settings?', default: true)) {
            $this->smartInfo('Configuration cancelled.');

            return self::SUCCESS;
        }

        // Save configuration
        $config = [
            'github_username' => $githubUsername,
            'php_namespace' => $phpNamespace,
            'author_email' => $authorEmail,
        ];

        $this->saveConfig($config);

        // Smart output based on mode
        if ($this->isNonInteractiveMode()) {
            return $this->jsonResponse($config);
        } else {
            $this->smartOutput($config, '✅ Component configuration saved!');
            $this->smartLine('   Use "component:config" anytime to update these settings.');

            return self::SUCCESS;
        }
    }

    protected function executeNonInteractive(): int
    {
        // Check if already configured
        if ($this->hasValidConfig()) {
            $config = $this->loadConfig();

            return $this->jsonResponse($config);
        }

        // Auto-detect and save configuration
        $config = [
            'github_username' => $this->detectGithubUsername(),
            'php_namespace' => $this->detectPhpNamespace($this->detectGithubUsername()),
            'author_email' => $this->detectAuthorEmail(),
        ];

        $this->saveConfig($config);

        return $this->jsonResponse($config);
    }

    private function hasValidConfig(): bool
    {
        $config = $this->loadConfig();

        return ! empty($config['github_username']) &&
               ! empty($config['php_namespace']) &&
               ! empty($config['author_email']);
    }

    private function loadConfig(): array
    {
        $configPath = $this->getConfigPath();

        return file_exists($configPath) ? json_decode(file_get_contents($configPath), true) : [];
    }

    private function detectGithubUsername(): string
    {
        // Try current config first
        $config = $this->loadConfig();
        if (! empty($config['github_username'])) {
            return $config['github_username'];
        }

        // Try config (which checks environment)
        if ($username = config('conduit.components.github_username')) {
            return $username;
        }

        // Try git config
        if ($username = $this->getGitConfig('user.name')) {
            return strtolower(str_replace(' ', '', $username));
        }

        // Guaranteed fallback
        return 'jordanpartridge';
    }

    private function detectPhpNamespace(string $githubUsername): string
    {
        // Try current config first
        $config = $this->loadConfig();
        if (! empty($config['php_namespace'])) {
            return $config['php_namespace'];
        }

        // Try config (which checks environment)
        if ($namespace = config('conduit.components.namespace')) {
            return $namespace;
        }

        // Generate from GitHub username
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $githubUsername)));
    }

    private function detectAuthorEmail(): string
    {
        // Try current config first
        $config = $this->loadConfig();
        if (! empty($config['author_email'])) {
            return $config['author_email'];
        }

        // Try config (which checks environment)
        if ($email = config('conduit.components.author_email')) {
            return $email;
        }

        // Try git config
        if ($email = $this->getGitConfig('user.email')) {
            return $email;
        }

        // Guaranteed fallback
        return 'jordan@partridge.rocks';
    }

    private function getGitConfig(string $key): ?string
    {
        // Use Process class for safer git config execution
        $process = new \Symfony\Component\Process\Process(['git', 'config', '--global', $key]);
        $process->run();

        return $process->isSuccessful() ? trim($process->getOutput()) : null;
    }

    private function saveConfig(array $settings): void
    {
        $configPath = $this->getConfigPath();
        $dir = dirname($configPath);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($configPath, json_encode($settings, JSON_PRETTY_PRINT));
    }

    private function getConfigPath(): string
    {
        return $_SERVER['HOME'].'/.config/conduit/component-config.json';
    }
}
