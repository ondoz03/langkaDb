<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\DTOs\Security;

use Illuminate\Contracts\Support\Arrayable;

/**
 * PIIFindingDTO — represents a PII-sensitive column finding
 */
readonly class PIIFindingDTO implements Arrayable
{
    public function __construct(
        public string $table,
        public string $column,
        public string $type,
        public string $category,     // email, phone, ssn, password, credit_card, address, etc.
        public string $severity,     // HIGH, MEDIUM, LOW
        public bool $hasConstraint,  // has NOT NULL or UNIQUE constraint?
        public string $recommendation,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            table: $data['table'],
            column: $data['column'],
            type: $data['type'],
            category: $data['category'],
            severity: $data['severity'],
            hasConstraint: (bool) ($data['has_constraint'] ?? false),
            recommendation: $data['recommendation'],
        );
    }

    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'column' => $this->column,
            'type' => $this->type,
            'category' => $this->category,
            'severity' => $this->severity,
            'has_constraint' => $this->hasConstraint,
            'recommendation' => $this->recommendation,
        ];
    }
}
