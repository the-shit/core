<?php

namespace App\Commands;

use Illuminate\Support\Facades\File;
use Carbon\Carbon;

use function Laravel\Prompts\table;

class LsCommand extends ConduitCommand
{
    protected $signature = 'ls {path?} {--json : Output as JSON} {--recent : Sort by recently modified} {--large : Sort by size} {--git : Show git status}';

    protected $description = '💩 List files and directories (but actually good)';

    protected function executeCommand(): int
    {
        $path = $this->argument('path') ?? getcwd();
        
        if (!is_dir($path)) {
            $this->forceOutput("💩 Path doesn't exist: {$path}", 'error');
            return self::FAILURE;
        }

        $files = $this->scanDirectory($path);
        
        if ($this->option('recent')) {
            $files = collect($files)->sortByDesc('modified')->values()->all();
        } elseif ($this->option('large')) {
            $files = collect($files)->sortByDesc('size')->values()->all();
        }

        if ($this->isNonInteractiveMode()) {
            return $this->jsonResponse(['files' => $files, 'path' => $path]);
        }

        return $this->displayInteractive($files, $path);
    }

    private function scanDirectory(string $path): array
    {
        $files = [];
        
        try {
            $items = scandir($path);
            
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                
                $fullPath = $path . DIRECTORY_SEPARATOR . $item;
                $stat = stat($fullPath);
                
                if ($stat === false) {
                    // Skip files we can't stat
                    continue;
                }
                
                $files[] = [
                    'name' => $item,
                    'type' => is_dir($fullPath) ? 'directory' : 'file',
                    'size' => $stat['size'],
                    'modified' => Carbon::createFromTimestamp($stat['mtime']),
                    'permissions' => $this->getPermissions($fullPath),
                    'icon' => $this->getIcon($fullPath),
                    'git_status' => $this->option('git') ? $this->getGitStatus($fullPath) : null,
                ];
            }
        } catch (\Exception $e) {
            $this->forceOutput("💩 Error reading directory: " . $e->getMessage(), 'error');
        }
        
        return $files;
    }

    private function getIcon(string $path): string
    {
        if (is_dir($path)) {
            // Special directories
            if (basename($path) === '.git') return '🔧';
            if (basename($path) === 'vendor') return '📦';
            if (basename($path) === 'node_modules') return '📦';
            if (basename($path) === 'tests') return '🧪';
            return '📁';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        return match($extension) {
            'php' => '🐘',
            'js', 'ts' => '💛',
            'json' => '📋',
            'md' => '📝',
            'txt' => '📄',
            'yml', 'yaml' => '⚙️',
            'env' => '🔐',
            'log' => '📜',
            'sql' => '🗄️',
            'png', 'jpg', 'jpeg', 'gif' => '🖼️',
            'mp3', 'wav' => '🎵',
            'mp4', 'avi' => '🎬',
            'zip', 'tar', 'gz' => '📦',
            'sh' => '💻',
            default => '📄'
        };
    }

    private function getPermissions(string $path): string
    {
        $perms = fileperms($path);
        $info = '';

        // Owner
        $info .= (($perms & 0x0100) ? 'r' : '-');
        $info .= (($perms & 0x0080) ? 'w' : '-');
        $info .= (($perms & 0x0040) ? 'x' : '-');

        // Group
        $info .= (($perms & 0x0020) ? 'r' : '-');
        $info .= (($perms & 0x0010) ? 'w' : '-');
        $info .= (($perms & 0x0008) ? 'x' : '-');

        // Others
        $info .= (($perms & 0x0004) ? 'r' : '-');
        $info .= (($perms & 0x0002) ? 'w' : '-');
        $info .= (($perms & 0x0001) ? 'x' : '-');

        return $info;
    }

    private function getGitStatus(string $path): ?string
    {
        if (!is_dir('.git') && !exec('git rev-parse --git-dir 2>/dev/null')) {
            return null;
        }

        $relativePath = str_replace(getcwd() . '/', '', $path);
        $status = trim(shell_exec('git status --porcelain ' . escapeshellarg($relativePath) . ' 2>/dev/null') ?? '');
        
        if (empty($status)) return '✅';
        
        return match(substr($status, 0, 2)) {
            '??' => '❓',
            'A ' => '➕',
            'M ' => '📝',
            'D ' => '❌',
            'R ' => '🔄',
            ' M' => '📝',
            ' D' => '❌',
            default => '⚡'
        };
    }


    private function formatSize(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor(log($bytes, 1024));
        $factor = min($factor, count($units) - 1);
        
        return sprintf("%.1f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }

    private function displayInteractive(array $files, string $path): int
    {
        $this->smartInfo("💩 SNIT File Browser - {$path}");
        $this->smartNewLine();

        if (empty($files)) {
            $this->smartLine('Empty directory (how sad)');
            return self::SUCCESS;
        }

        // Prepare table rows
        $rows = [];
        foreach ($files as $file) {
            $sizeStr = $file['type'] === 'directory' ? '-' : $this->formatSize($file['size']);
            $modifiedStr = $file['modified']->diffForHumans();
            $nameWithIcon = $file['icon'] . ' ' . $file['name'];
            
            // Add git status if enabled
            if ($this->option('git') && $file['git_status']) {
                $nameWithIcon .= ' ' . $file['git_status'];
            }

            $rows[] = [
                substr($nameWithIcon, 0, 30),
                $sizeStr,
                substr($modifiedStr, 0, 15),
                $file['permissions']
            ];
        }

        // Use Laravel Prompts table
        table(
            ['📄 Name', '📊 Size', '📅 Modified', '🔐 Perms'],
            $rows
        );

        $this->smartNewLine();
        $this->smartLine("💡 Tip: Use --json for machine-readable output");
        $this->smartLine("💡 Tip: Use --recent, --large, or --git for different views");

        return self::SUCCESS;
    }
}
