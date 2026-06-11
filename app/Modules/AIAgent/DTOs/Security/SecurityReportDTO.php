<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\DTOs\Security;

use Illuminate\Contracts\Support\Arrayable;

/**
 * SecurityReportDTO — aggregates all security analysis findings into a single report
 */
readonly class SecurityReportDTO implements Arrayable
{
    /**
     * @param  PermissionFindingDTO[]  $permissionFindings
     * @param  PIIFindingDTO[]  $piiFindings
     */
    public function __construct(
        public int $score,                        // Overall security score 0–100
        public array $permissionFindings = [],    // PermissionFindingDTO[]
        public array $piiFindings = [],           // PIIFindingDTO[]
        public array $constraintIssues = [],      // [['severity' => '...', 'message' => '...']]
        public array $recommendations = [],       // [['priority' => '...', 'message' => '...']]
        public array $metadata = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            score: (int) ($data['score'] ?? 0),
            permissionFindings: array_map(
                fn ($f) => $f instanceof PermissionFindingDTO ? $f : PermissionFindingDTO::fromArray($f),
                $data['permission_findings'] ?? [],
            ),
            piiFindings: array_map(
                fn ($f) => $f instanceof PIIFindingDTO ? $f : PIIFindingDTO::fromArray($f),
                $data['pii_findings'] ?? [],
            ),
            constraintIssues: $data['constraint_issues'] ?? [],
            recommendations: $data['recommendations'] ?? [],
            metadata: $data['metadata'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'permission_findings' => array_map(
                fn (PermissionFindingDTO $f) => $f->toArray(),
                $this->permissionFindings,
            ),
            'pii_findings' => array_map(
                fn (PIIFindingDTO $f) => $f->toArray(),
                $this->piiFindings,
            ),
            'constraint_issues' => $this->constraintIssues,
            'recommendations' => $this->recommendations,
            'metadata' => $this->metadata,
        ];
    }
}
