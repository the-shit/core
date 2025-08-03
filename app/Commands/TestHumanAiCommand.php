<?php

namespace App\Commands;

class TestHumanAiCommand extends ConduitCommand
{
    protected $signature = 'test:human-ai {--json : Output as JSON}';

    protected $description = 'Test the Human-AI collaboration pattern';

    protected function executeCommand(): int
    {
        $this->smartInfo('🧪 Testing Human-AI Collaboration Pattern');
        $this->smartNewLine();

        // Test smart text input
        $name = $this->smartText(
            label: 'What is your name?',
            placeholder: 'e.g., Jordan',
            default: 'Jordan Partridge',
            required: true,
            hint: 'This will use the default in AI mode'
        );

        // Test smart confirm
        $shouldContinue = $this->smartConfirm(
            label: 'Do you want to continue?',
            default: true
        );

        if (!$shouldContinue) {
            $this->smartInfo('Operation cancelled.');
            return self::SUCCESS;
        }

        // Test smart output
        $result = [
            'name' => $name,
            'mode' => $this->isNonInteractiveMode() ? 'ai-agent' : 'human-interactive',
            'user_agent' => $this->getUserAgent(),
            'timestamp' => now()->toISOString(),
            'pattern_works' => true
        ];

        if ($this->isNonInteractiveMode()) {
            return $this->jsonResponse($result);
        } else {
            $this->smartOutput($result, '✅ Human-AI collaboration pattern works!');
            $this->smartNewLine();
            $this->smartLine('🎉 This proves the architecture is sound!');
            return self::SUCCESS;
        }
    }

    protected function executeNonInteractive(): int
    {
        // Specialized behavior for AI agents
        $result = [
            'mode' => 'ai-specialized',
            'user_agent' => $this->getUserAgent(),
            'message' => 'AI agent executed specialized non-interactive logic',
            'defaults_used' => true,
            'timestamp' => now()->toISOString()
        ];

        return $this->jsonResponse($result);
    }
}
