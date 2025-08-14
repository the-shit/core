<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class KnowledgeCaptureCommand extends Command
{
    protected $signature = 'knowledge:capture 
                            {content : The content to capture}
                            {--tags=the-shit : Comma-separated tags}
                            {--to=log : Destination (log|conduit|both)}';

    protected $description = 'Capture knowledge from THE SHIT to Conduit or log';

    public function handle(): int
    {
        $content = $this->argument('content');
        $tags = $this->option('tags');
        $destination = $this->option('to');

        $captured = false;

        // Try Conduit first if requested
        if (in_array($destination, ['conduit', 'both'])) {
            $conduitPath = $this->findConduit();
            if ($conduitPath) {
                $command = sprintf(
                    '%s knowledge:add %s --tags=%s 2>&1',
                    $conduitPath,
                    escapeshellarg($content),
                    escapeshellarg($tags)
                );

                $output = shell_exec($command);
                if (strpos($output, 'Entry #') !== false) {
                    $this->info('✓ Captured to Conduit');
                    $captured = true;
                }
            }
        }

        // Log to file if requested or as fallback
        if (in_array($destination, ['log', 'both']) || ! $captured) {
            $logFile = getenv('HOME').'/.shit-knowledge.log';
            $entry = sprintf(
                "[%s] %s (tags: %s)\n",
                date('Y-m-d H:i:s'),
                $content,
                $tags
            );
            file_put_contents($logFile, $entry, FILE_APPEND);
            $this->info('✓ Captured to log file');
        }

        // Emit event for other components
        $this->call('event:emit', [
            'component' => 'knowledge',
            'event' => 'captured',
            'data' => json_encode([
                'content' => $content,
                'tags' => $tags,
                'timestamp' => time(),
            ]),
        ]);

        return 0;
    }

    private function findConduit(): ?string
    {
        $paths = [
            getenv('HOME').'/bin/conduit',
            getenv('HOME').'/.local/bin/conduit',
            getenv('HOME').'/packages/conduit/conduit',
            '/usr/local/bin/conduit',
        ];

        foreach ($paths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }
}
