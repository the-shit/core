<?php

namespace App\Commands;

class DetectionTestCommand extends ConduitCommand
{
    protected $signature = 'debug:detection {--json : Output as JSON}';

    protected $description = 'Debug the smart detection logic';

    protected function executeCommand(): int
    {
        $detectionResults = [
            'is_interactive_input' => $this->input->isInteractive(),
            'has_no_interaction_flag' => in_array('--no-interaction', $GLOBALS['argv'] ?? []),
            'has_n_flag' => in_array('-n', $GLOBALS['argv'] ?? []),
            'user_agent' => $this->getUserAgent(),
            'user_agent_not_human' => $this->getUserAgent() !== 'human',
            'final_is_non_interactive' => $this->isNonInteractiveMode(),
            'argv' => $GLOBALS['argv'] ?? [],
            'environment_vars' => [
                'CONDUIT_USER_AGENT' => $_SERVER['CONDUIT_USER_AGENT'] ?? null,
                'config_CONDUIT_USER_AGENT' => config('conduit.user_agent'),
            ],
        ];

        if ($this->isNonInteractiveMode()) {
            return $this->jsonResponse($detectionResults);
        } else {
            $this->info('🔍 Smart Detection Debug Results:');
            $this->newLine();

            foreach ($detectionResults as $key => $value) {
                if ($key === 'argv' || $key === 'environment_vars') {
                    $this->line("   {$key}: ".json_encode($value));
                } else {
                    $status = is_bool($value) ? ($value ? '✅ TRUE' : '❌ FALSE') : $value;
                    $this->line("   {$key}: {$status}");
                }
            }

            $this->newLine();
            $this->line('💡 Detection Logic:');
            $this->line('   Non-interactive if ANY of these are true:');
            $this->line('   • !input->isInteractive()');
            $this->line('   • --no-interaction flag in argv');
            $this->line('   • -n flag in argv');
            $this->line('   • user_agent !== "human"');

            return self::SUCCESS;
        }
    }
}
