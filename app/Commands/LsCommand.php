<?php

namespace App\Commands;

use Illuminate\Support\Facades\File;
use Carbon\Carbon;

use function Laravel\Prompts\table;

class LsCommand extends ConduitCommand
{
    protected $signature = 'ls {path?} {--json : Output as JSON} {--recent : Sort by recently modified} {--large : Sort by size} {--git : Show git status} {--octal : Show octal permissions} {--detailed-perms : Show full rwx permissions} {--guide : Show the sexy options guide}';

    protected $description = '💩 List files and directories (but actually good)';

    protected function executeCommand(): int
    {
        // Check for custom guide first
        if ($this->option('guide')) {
            return $this->showSexyHelp();
        }
        
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
    
    private function showSexyHelp(): int
    {
        $this->smartInfo("💩 SNIT ls - The file lister that doesn't lie to you");
        $this->smartNewLine();
        
        $this->smartLine("Usage: ./💩 ls [path] [options]");
        $this->smartNewLine();
        
        table(
            ['🚩 Flag', '📝 Description', '💡 Example'],
            [
                ['--json', 'Output as machine-readable JSON', './💩 ls --json'],
                ['--recent', 'Sort by recently modified files first', './💩 ls --recent'],
                ['--large', 'Sort by largest files first', './💩 ls --large'],
                ['--git', 'Show git status indicators', './💩 ls --git'],
                ['--octal', 'Show permissions as 755 format', './💩 ls --octal'],
                ['--detailed-perms', 'Show full rwxr-xr-x format', './💩 ls --detailed-perms'],
                ['--guide', 'Show this sexy options guide', './💩 ls --guide'],
            ]
        );
        
        $this->smartNewLine();
        $this->smartLine("🎭 Permission Emojis:");
        
        table(
            ['🎨 Emoji', '📊 Octal', '📝 Description'],
            [
                ['📁', '755', 'Directory with normal access'],
                ['🔓', '755', 'Executable file'],
                ['📖', '644', 'Standard readable file'],
                ['🔒', '600', 'Private file (owner only)'],
                ['🏠', '700', 'Private directory/file'],
                ['📝', '666', 'World-writable file'],
                ['🚨', '777', 'DANGEROUS: World-writable!'],
                ['👁️', '444', 'Read-only file'],
                ['🔍', '555', 'Read/execute only'],
                ['🚫', '000', 'No permissions'],
            ]
        );
        
        $this->smartNewLine();
        $this->smartLine("💩 Finally, a file lister that doesn't pretend to be enterprise-grade.");
        
        return self::SUCCESS;
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
        $perms = fileperms($path) & 0777;
        $permString = $this->formatPermissionString($perms);
        $isDir = is_dir($path);
        
        // Choose format based on flags
        if ($this->option('detailed-perms')) {
            return $this->getPermissionEmoji($perms, $isDir) . ' ' . $permString;
        } elseif ($this->option('octal')) {
            return $this->getPermissionEmoji($perms, $isDir) . ' ' . sprintf('%03o', $perms);
        }
        
        // Default: emoji + description (different for dirs vs files)
        if ($isDir) {
            return match($perms) {
                0755 => '📁 Dir Access',     // rwxr-xr-x
                0700 => '🏠 Private Dir',    // rwx------
                0777 => '🚨 World Write!',   // rwxrwxrwx (dangerous!)
                0555 => '🔍 Read Only',      // r-xr-xr-x
                0000 => '🚫 No Access',      // ---------
                default => $this->getPermissionEmoji($perms, $isDir) . ' ' . sprintf('%03o', $perms)
            };
        } else {
            return match($perms) {
                0755 => '🔓 Executable',     // rwxr-xr-x
                0644 => '📖 Standard',       // rw-r--r--
                0600 => '🔒 Private',        // rw-------
                0777 => '🚨 Dangerous!',     // rwxrwxrwx (world writable)
                0700 => '🏠 Owner Only',     // rwx------
                0666 => '📝 World Edit',     // rw-rw-rw-
                0555 => '🔍 Read/Run',       // r-xr-xr-x
                0444 => '👁️ Read Only',      // r--r--r--
                0000 => '🚫 No Access',      // ---------
                default => $this->getPermissionEmoji($perms, $isDir) . ' ' . sprintf('%03o', $perms)
            };
        }
    }
    
    private function getPermissionEmoji(int $perms, bool $isDir = false): string
    {
        if ($isDir) {
            return match($perms) {
                0755 => '📁',  // Directory access
                0700 => '🏠',  // Private directory
                0777 => '🚨',  // Dangerous!
                0555 => '🔍',  // Read-only directory
                0000 => '🚫',  // No access
                default => '📂'  // Generic folder for uncommon perms
            };
        } else {
            return match($perms) {
                0755 => '🔓',  // Executable file
                0644 => '📖',  // Standard file
                0600 => '🔒',  // Private file
                0777 => '🚨',  // Dangerous!
                0700 => '🏠',  // Owner only
                0666 => '📝',  // World writable file
                0555 => '🔍',  // Read/execute
                0444 => '👁️',  // Read-only
                0000 => '🚫',  // No permissions
                default => '⚙️'  // Generic gear for uncommon perms
            };
        }
    }
    
    private function formatPermissionString(int $perms): string
    {
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
