<?php

namespace App\ValueObjects;

/**
 * Value object representing the result of a component operation
 */
final readonly class ComponentResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public array $data = [],
        public ?string $hint = null,
        public int $exitCode = 0
    ) {}

    public static function success(string $message, array $data = [], ?string $hint = null): self
    {
        return new self(
            success: true,
            message: $message,
            data: $data,
            hint: $hint,
            exitCode: 0
        );
    }

    public static function failure(string $message, ?string $hint = null, int $exitCode = 1): self
    {
        return new self(
            success: false,
            message: $message,
            data: [],
            hint: $hint,
            exitCode: $exitCode
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
            'hint' => $this->hint,
            'exit_code' => $this->exitCode,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}
