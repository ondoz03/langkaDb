<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\DTOs\Security;

use Illuminate\Contracts\Support\Arrayable;

/**
 * PermissionFindingDTO — represents a single permission/privilege security finding
 */
readonly class PermissionFindingDTO implements Arrayable
{
    public function __construct(
        public string $user,
        public string $role,
        public string $severity,   // HIGH, MEDIUM, LOW
        public string $issue,
        public string $recommendation,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            user: $data['user'],
            role: $data['role'],
            severity: $data['severity'],
            issue: $data['issue'],
            recommendation: $data['recommendation'],
        );
    }

    public function toArray(): array
    {
        return [
            'user' => $this->user,
            'role' => $this->role,
            'severity' => $this->severity,
            'issue' => $this->issue,
            'recommendation' => $this->recommendation,
        ];
    }
}
