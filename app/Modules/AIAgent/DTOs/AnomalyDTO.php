<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\DTOs;

use Illuminate\Contracts\Support\Arrayable;

readonly class AnomalyDTO implements Arrayable
{
    public function __construct(
        public string $type,
        public string $severity,
        public string $message,
        public ?string $table = null,
        public array $context = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: (string) ($data['type'] ?? 'unknown'),
            severity: (string) ($data['severity'] ?? 'low'),
            message: (string) ($data['message'] ?? ''),
            table: isset($data['table']) ? (string) $data['table'] : null,
            context: (array) ($data['context'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'severity' => $this->severity,
            'message' => $this->message,
            'table' => $this->table,
            'context' => $this->context,
        ];
    }
}
