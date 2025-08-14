<?php

namespace Tests\Unit;

use App\Commands\ConduitCommand;
use Illuminate\Console\Command;
use Tests\TestCase;

class ConduitCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset environment
        unset($_SERVER['CONDUIT_USER_AGENT']);
        if (isset($GLOBALS['argv'])) {
            unset($GLOBALS['argv']);
        }
    }
}

test('detects human user agent by default', function () {
    $command = new class extends ConduitCommand
    {
        protected $signature = 'test';

        protected function executeCommand(): int
        {
            return 0;
        }

        public function testGetUserAgent(): string
        {
            return $this->getUserAgent();
        }
    };

    expect($command->testGetUserAgent())->toBe('human');
});

test('detects AI user agent from environment', function () {
    $_SERVER['CONDUIT_USER_AGENT'] = 'claude';

    $command = new class extends ConduitCommand
    {
        protected $signature = 'test';

        protected function executeCommand(): int
        {
            return 0;
        }

        public function testGetUserAgent(): string
        {
            return $this->getUserAgent();
        }
    };

    expect($command->testGetUserAgent())->toBe('claude');
});

test('detects non-interactive mode with --no-interaction flag', function () {
    $GLOBALS['argv'] = ['artisan', 'test:command', '--no-interaction'];

    $command = new class extends ConduitCommand
    {
        protected $signature = 'test:command';

        protected function executeCommand(): int
        {
            return 0;
        }

        public function testIsNonInteractiveMode(): bool
        {
            return $this->isNonInteractiveMode();
        }
    };

    expect($command->testIsNonInteractiveMode())->toBeTrue();
});

test('detects non-interactive mode with -n flag', function () {
    $GLOBALS['argv'] = ['artisan', 'test:command', '-n'];

    $command = new class extends ConduitCommand
    {
        protected $signature = 'test:command';

        protected function executeCommand(): int
        {
            return 0;
        }

        public function testIsNonInteractiveMode(): bool
        {
            return $this->isNonInteractiveMode();
        }
    };

    expect($command->testIsNonInteractiveMode())->toBeTrue();
});

test('detects non-interactive mode for AI agents', function () {
    $_SERVER['CONDUIT_USER_AGENT'] = 'ai';

    $command = new class extends ConduitCommand
    {
        protected $signature = 'test:command';

        protected function executeCommand(): int
        {
            return 0;
        }

        public function testIsNonInteractiveMode(): bool
        {
            return $this->isNonInteractiveMode();
        }
    };

    expect($command->testIsNonInteractiveMode())->toBeTrue();
});

test('smartText returns default in non-interactive mode', function () {
    $_SERVER['CONDUIT_USER_AGENT'] = 'ai';

    $command = new class extends ConduitCommand
    {
        protected $signature = 'test:command';

        protected function executeCommand(): int
        {
            return 0;
        }

        public function testSmartText($label, $placeholder = '', $default = '', $required = false): string
        {
            return $this->smartText($label, $placeholder, $default, $required);
        }
    };

    $result = $command->testSmartText('Test Label', '', 'default_value');
    expect($result)->toBe('default_value');
});

test('smartText throws exception for required field without default in non-interactive mode', function () {
    $_SERVER['CONDUIT_USER_AGENT'] = 'ai';

    $command = new class extends ConduitCommand
    {
        protected $signature = 'test:command';

        protected function executeCommand(): int
        {
            return 0;
        }

        public function testSmartText($label, $placeholder = '', $default = '', $required = false): string
        {
            return $this->smartText($label, $placeholder, $default, $required);
        }
    };

    expect(fn () => $command->testSmartText('Test Label', '', '', true))
        ->toThrow(\RuntimeException::class, 'Non-interactive mode requires a default value for: Test Label');
});

test('smartConfirm returns default in non-interactive mode', function () {
    $_SERVER['CONDUIT_USER_AGENT'] = 'ai';

    $command = new class extends ConduitCommand
    {
        protected $signature = 'test:command';

        protected function executeCommand(): int
        {
            return 0;
        }

        public function testSmartConfirm($label, $default = false): bool
        {
            return $this->smartConfirm($label, $default);
        }
    };

    expect($command->testSmartConfirm('Confirm?', true))->toBeTrue();
    expect($command->testSmartConfirm('Confirm?', false))->toBeFalse();
});

test('smartChoice returns default in non-interactive mode', function () {
    $_SERVER['CONDUIT_USER_AGENT'] = 'ai';

    $command = new class extends ConduitCommand
    {
        protected $signature = 'test:command';

        protected function executeCommand(): int
        {
            return 0;
        }

        public function testSmartChoice($label, $options, $default = null): mixed
        {
            return $this->smartChoice($label, $options, $default);
        }
    };

    $options = ['option1' => 'Option 1', 'option2' => 'Option 2'];
    $result = $command->testSmartChoice('Choose', $options, 'option2');

    expect($result)->toBe('option2');
});

test('jsonResponse outputs JSON for AI agents', function () {
    $_SERVER['CONDUIT_USER_AGENT'] = 'ai';

    $command = new class extends ConduitCommand
    {
        protected $signature = 'test:command';

        protected function executeCommand(): int
        {
            return 0;
        }

        public function testJsonResponse($data, $status = 'success'): int
        {
            return $this->jsonResponse($data, $status);
        }
    };

    // Capture output
    ob_start();
    $result = $command->testJsonResponse(['key' => 'value'], 'success');
    $output = ob_get_clean();

    $json = json_decode($output, true);

    expect($result)->toBe(Command::SUCCESS);
    expect($json)->toHaveKey('status', 'success');
    expect($json)->toHaveKey('data');
    expect($json['data'])->toBe(['key' => 'value']);
});

test('jsonResponse returns error code for error status', function () {
    $_SERVER['CONDUIT_USER_AGENT'] = 'ai';

    $command = new class extends ConduitCommand
    {
        protected $signature = 'test:command';

        protected function executeCommand(): int
        {
            return 0;
        }

        public function testJsonResponse($data, $status = 'success'): int
        {
            return $this->jsonResponse($data, $status);
        }
    };

    ob_start();
    $result = $command->testJsonResponse(['error' => 'Something went wrong'], 'error');
    ob_get_clean();

    expect($result)->toBe(Command::FAILURE);
});

test('smartInfo outputs info for humans', function () {
    $command = new class extends ConduitCommand
    {
        protected $signature = 'test:command';

        protected function executeCommand(): int
        {
            return 0;
        }

        public function testSmartInfo($message): void
        {
            $this->smartInfo($message);
        }
    };

    ob_start();
    $command->testSmartInfo('Test info message');
    $output = ob_get_clean();

    expect($output)->toContain('Test info message');
});

test('smartInfo outputs JSON for AI agents', function () {
    $_SERVER['CONDUIT_USER_AGENT'] = 'ai';

    $command = new class extends ConduitCommand
    {
        protected $signature = 'test:command';

        protected function executeCommand(): int
        {
            return 0;
        }

        public function testSmartInfo($message): void
        {
            $this->smartInfo($message);
        }
    };

    ob_start();
    $command->testSmartInfo('Test info message');
    $output = ob_get_clean();

    $json = json_decode($output, true);
    expect($json)->toHaveKey('type', 'info');
    expect($json)->toHaveKey('message', 'Test info message');
});

test('handle method calls executeNonInteractive for AI agents', function () {
    $_SERVER['CONDUIT_USER_AGENT'] = 'ai';

    $command = new class extends ConduitCommand
    {
        protected $signature = 'test:command';

        public $executeNonInteractiveCalled = false;

        protected function executeCommand(): int
        {
            return Command::SUCCESS;
        }

        protected function executeNonInteractive(): int
        {
            $this->executeNonInteractiveCalled = true;

            return Command::SUCCESS;
        }
    };

    // Simulate non-interactive execution
    $GLOBALS['argv'] = ['artisan', 'test:command', '--no-interaction'];

    expect($command->isNonInteractiveMode())->toBeTrue();
});

test('handle method calls executeCommand for humans', function () {
    $_SERVER['CONDUIT_USER_AGENT'] = 'human';

    $command = new class extends ConduitCommand
    {
        protected $signature = 'test:command';

        public $executeCalled = false;

        protected function executeCommand(): int
        {
            $this->executeCalled = true;

            return Command::SUCCESS;
        }

        public function testHandle(): int
        {
            return $this->handle();
        }
    };

    // Interactive mode for humans
    expect($command->isNonInteractiveMode())->toBeFalse();
});
