<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Agents;

use App\Modules\AIAgent\Contracts\AgentInterface;
use App\Modules\AIAgent\DTOs\AgentResultDTO;
use App\Modules\AIAgent\DTOs\Security\PermissionFindingDTO;
use App\Modules\AIAgent\DTOs\Security\PIIFindingDTO;
use App\Modules\AIAgent\DTOs\Security\SecurityReportDTO;
use App\Modules\AIAgent\Services\AIRouter;
use App\Modules\Schema\DTOs\ColumnDTO;
use App\Modules\Schema\DTOs\SchemaContextDTO;
use App\Modules\Schema\DTOs\TableDTO;

/**
 * SecurityAgent — analyzes database schemas for security risks:
 * permission over-privilege, PII exposure, and constraint coverage.
 *
 * Implements AgentInterface for standard agent dispatch, with additional
 * fine-grained methods for targeted security scans.
 */
class SecurityAgent implements AgentInterface
{
    /** Known PII column name patterns grouped by category */
    private const PII_PATTERNS = [
        'email' => [
            'pattern' => '/^(email|e_mail|mail_address|contact_email)$/i',
            'severity' => 'MEDIUM',
        ],
        'phone' => [
            'pattern' => '/^(phone|telephone|mobile|phone_number|cell|cellphone|contact_no)$/i',
            'severity' => 'MEDIUM',
        ],
        'password' => [
            'pattern' => '/^(password|passwd|pwd|pass|user_password|pass_hash)$/i',
            'severity' => 'HIGH',
        ],
        'ssn' => [
            'pattern' => '/^(ssn|social_security|tax_id|tin|national_id|nik|ktp)$/i',
            'severity' => 'HIGH',
        ],
        'credit_card' => [
            'pattern' => '/^(credit_card|card_number|cc_number|ccn|pan|card_no)$/i',
            'severity' => 'HIGH',
        ],
        'bank_account' => [
            'pattern' => '/^(bank_account|account_number|iban|routing_number|bic|swift)$/i',
            'severity' => 'HIGH',
        ],
        'passport' => [
            'pattern' => '/^(passport|passport_number|visa_number)$/i',
            'severity' => 'HIGH',
        ],
        'token' => [
            'pattern' => '/^(token|api_key|api_secret|access_token|refresh_token|secret_key)$/i',
            'severity' => 'HIGH',
        ],
        'address' => [
            'pattern' => '/^(address|street|city|state|province|zip|postal_code|country)$/i',
            'severity' => 'LOW',
        ],
        'birth_date' => [
            'pattern' => '/^(birth_date|date_of_birth|dob|birthday|born)$/i',
            'severity' => 'MEDIUM',
        ],
        'ip_address' => [
            'pattern' => '/^(ip_address|ip|client_ip|remote_addr|host)$/i',
            'severity' => 'LOW',
        ],
    ];

    /** Tables that may represent user/role/permission entities */
    private const USER_TABLE_PATTERNS = [
        '/^users$/i',
        '/^user_/i',
        '/^auth_/i',
        '/^roles$/i',
        '/^permissions$/i',
        '/^role_user$/i',
        '/^user_role/i',
        '/^role_permission/i',
    ];

    /** Columns that indicate privilege escalation risk */
    private const PRIVILEGE_COLUMNS = [
        '/^is_admin$/i',
        '/^is_superuser$/i',
        '/^role$/i',
        '/^permissions$/i',
        '/^can_.+$/i',
    ];

    public function __construct(
        private readonly AIRouter $router,
    ) {}

    /**
     * Main entry point per AgentInterface.
     * Performs full security analysis and maps results to AgentResultDTO.
     */
    public function analyze(SchemaContextDTO $context, ?string $apiKey = null, string $provider = 'rule'): AgentResultDTO
    {
        // Attempt AI-enhanced analysis if API key is available
        if ($apiKey) {
            $prompt = $this->buildPrompt($context);
            $response = $this->router->route('security_analysis', $prompt, $this->systemPrompt(), $apiKey, $provider);
            $parsed = $this->parseResponse($response);

            return new AgentResultDTO(
                agent: 'security',
                findings: $parsed['findings'] ?? [],
                recommendations: $parsed['recommendations'] ?? [],
                score: $parsed['score'] ?? $this->calculateScore([], [], []),
                metadata: ['security_report' => $parsed['report'] ?? []],
            );
        }

        // Rule-based analysis (default)
        $report = $this->generateReport($context);

        $findings = [];
        foreach ($report->permissionFindings as $pf) {
            $findings[] = [
                'severity' => $pf->severity,
                'message' => "[Permission] {$pf->issue} (user: {$pf->user}, role: {$pf->role})",
            ];
        }
        foreach ($report->piiFindings as $pii) {
            $findings[] = [
                'severity' => $pii->severity,
                'message' => "[PII] {$pii->category} column '{$pii->column}' found in table '{$pii->table}'".($pii->hasConstraint ? ' (constrained)' : ' (unconstrained)'),
            ];
        }
        foreach ($report->constraintIssues as $ci) {
            $findings[] = [
                'severity' => $ci['severity'],
                'message' => "[Constraint] {$ci['message']}",
            ];
        }

        return new AgentResultDTO(
            agent: 'security',
            findings: $findings,
            recommendations: $report->recommendations,
            score: $report->score,
            metadata: ['security_report' => $report->toArray()],
        );
    }

    /**
     * Analyze user permissions and roles from schema context.
     * Detects over-privileged accounts, missing role structures, and privilege columns.
     *
     * @return PermissionFindingDTO[]
     */
    public function analyzePermissions(SchemaContextDTO $context): array
    {
        $findings = [];
        $tables = $context->tables;

        // Identify user/role/permission tables
        $userTables = [];
        $roleTables = [];
        $pivotTables = [];

        foreach ($tables as $table) {
            $name = $table->name;
            if (preg_match('/^roles$/i', $name)) {
                $roleTables[] = $table;
            } elseif (preg_match('/^users$/i', $name)) {
                $userTables[] = $table;
            } elseif (preg_match('/^(role_user|user_role|user_roles|role_permission|permission_role|permissions)$/i', $name)) {
                $pivotTables[] = $table;
            } elseif (preg_match('/^user_/i', $name) || preg_match('/^auth_/i', $name)) {
                $userTables[] = $table;
            }
        }

        // No user tables found — potential issue
        if (empty($userTables)) {
            $findings[] = new PermissionFindingDTO(
                user: 'N/A',
                role: 'N/A',
                severity: 'MEDIUM',
                issue: 'No user/authentication tables detected in schema. Access control may be unstructured.',
                recommendation: 'Implement a proper user management system with role-based access control (RBAC).',
            );
        }

        // Check each user table for privilege columns and structure
        foreach ($userTables as $table) {
            foreach ($table->columns as $column) {
                foreach (self::PRIVILEGE_COLUMNS as $pattern) {
                    if (preg_match($pattern, $column->name)) {
                        $findings[] = new PermissionFindingDTO(
                            user: "table:{$table->name}",
                            role: $column->name,
                            severity: 'HIGH',
                            issue: "Direct privilege column '{$column->name}' in '{$table->name}' suggests ad-hoc permission management. Use RBAC instead.",
                            recommendation: "Replace inline '{$column->name}' with a proper roles/permissions association table.",
                        );
                    }
                }
            }
        }

        // Check for role-based access control structure
        if (! empty($userTables) && empty($roleTables)) {
            $findings[] = new PermissionFindingDTO(
                user: 'N/A',
                role: 'N/A',
                severity: 'MEDIUM',
                issue: 'User tables exist but no dedicated roles table found. The application may lack proper RBAC.',
                recommendation: 'Create a roles table and associate users via a user_roles pivot table for proper access control.',
            );
        }

        // Check for missing pivot between users and roles
        if (! empty($userTables) && ! empty($roleTables) && empty($pivotTables)) {
            $findings[] = new PermissionFindingDTO(
                user: 'N/A',
                role: 'N/A',
                severity: 'MEDIUM',
                issue: 'Users and roles tables exist but no pivot/association table found. Role assignment is likely handled inline.',
                recommendation: 'Create a role_user pivot table to manage many-to-many user-role assignments cleanly.',
            );
        }

        // Check for columns named "root", "superuser", "administrator" in user tables
        foreach ($userTables as $table) {
            foreach ($table->columns as $column) {
                if (preg_match('/^(username|email)$/i', $column->name)) {
                    // Check column comment for hints of shared/root access
                    $comment = $column->comment;
                    if ($comment && preg_match('/root|admin|superuser|shared/i', $comment)) {
                        $findings[] = new PermissionFindingDTO(
                            user: "column:{$table->name}.{$column->name}",
                            role: 'N/A',
                            severity: 'HIGH',
                            issue: "Column '{$column->name}' in '{$table->name}' has comment suggesting root/admin shared access: '{$comment}'.",
                            recommendation: 'Avoid shared root accounts. Each user should have a unique identity with appropriate role assignments.',
                        );
                    }
                }
            }
        }

        return $findings;
    }

    /**
     * Detect PII-sensitive columns based on naming conventions.
     *
     * @return PIIFindingDTO[]
     */
    public function detectPIIExposure(SchemaContextDTO $context): array
    {
        $findings = [];

        foreach ($context->tables as $table) {
            foreach ($table->columns as $column) {
                $piiMatch = $this->matchPiiPattern($column->name);

                if ($piiMatch === null) {
                    continue;
                }

                $hasConstraint = ! $column->nullable;
                $hasUnique = $this->hasUniqueIndex($table, $column->name);

                $recommendation = match ($piiMatch['category']) {
                    'password' => "Consider hashing '{$column->name}' using bcrypt/argon2. Never store plain-text passwords.",
                    'credit_card' => "PCI DSS compliance required. Encrypt '{$column->name}' at rest and restrict access.",
                    'ssn' => "Encrypt '{$column->name}' at rest. Mask in logs and UI. Consider tokenization.",
                    'token' => "Ensure '{$column->name}' values are hashed before storage. Never log or expose in URLs.",
                    'email' => $hasUnique ? null : "Add a UNIQUE index on '{$column->name}' to prevent duplicate emails.",
                    'phone' => $hasUnique ? null : "Consider adding a UNIQUE index on '{$column->name}' for data integrity.",
                    default => "Ensure column '{$column->name}' is properly secured and access-controlled.",
                };

                if ($recommendation === null) {
                    $recommendation = "Column '{$column->name}' is properly constrained.";
                }

                $findings[] = new PIIFindingDTO(
                    table: $table->name,
                    column: $column->name,
                    type: $column->type,
                    category: $piiMatch['category'],
                    severity: match ($piiMatch['category']) {
                        'email', 'phone', 'birth_date' => 'MEDIUM',
                        'address', 'ip_address' => 'LOW',
                        default => 'HIGH',
                    },
                    hasConstraint: $hasConstraint || $hasUnique,
                    recommendation: $recommendation,
                );
            }
        }

        return $findings;
    }

    /**
     * Audit constraint coverage for tables and columns.
     *
     * @return array [['severity' => '...', 'message' => '...']]
     */
    public function auditConstraints(SchemaContextDTO $context): array
    {
        $issues = [];

        foreach ($context->tables as $table) {
            $nonNullableCount = 0;
            $nullableCount = 0;

            foreach ($table->columns as $column) {
                if ($column->nullable) {
                    $nullableCount++;
                } else {
                    $nonNullableCount++;
                }

                // Check for oversized VARCHAR that should be TEXT
                if (preg_match('/^varchar\((\d+)\)$/i', $column->type, $m)) {
                    $length = (int) $m[1];
                    if ($length > 500 && ! $column->nullable) {
                        $issues[] = [
                            'severity' => 'LOW',
                            'message' => "Column '{$table->name}.{$column->name}' uses VARCHAR({$length}) which is large. Consider TEXT or reduce length.",
                        ];
                    }
                }
            }

            // Flag tables with excessive nullable columns
            $totalColumns = $nonNullableCount + $nullableCount;
            if ($totalColumns > 0 && $nullableCount / $totalColumns > 0.7) {
                $issues[] = [
                    'severity' => 'MEDIUM',
                    'message' => "Table '{$table->name}' has {$nullableCount}/{$totalColumns} nullable columns ({$this->percent($nullableCount, $totalColumns)}%). Consider which columns should be NOT NULL for data integrity.",
                ];
            }

            // Check for missing primary key
            $hasPrimary = false;
            foreach ($table->columns as $column) {
                if ($column->primary) {
                    $hasPrimary = true;
                    break;
                }
            }
            if (! $hasPrimary && preg_match('/^_|^tmp_|^temp_/i', $table->name) === 0) {
                $issues[] = [
                    'severity' => 'HIGH',
                    'message' => "Table '{$table->name}' has no primary key. Every table should have a primary key for data integrity and performance.",
                ];
            }

            // Check for missing indexes on foreign key columns
            $fkColumnNames = [];
            foreach ($table->columns as $column) {
                if (preg_match('/_id$/i', $column->name) && ! $column->primary) {
                    $fkColumnNames[] = $column->name;
                }
            }
            $indexedColumns = [];
            foreach ($table->indexes as $index) {
                $indexedColumns = array_merge($indexedColumns, $index->columns);
            }
            foreach ($fkColumnNames as $fkCol) {
                if (! in_array($fkCol, $indexedColumns, true)) {
                    $issues[] = [
                        'severity' => 'MEDIUM',
                        'message' => "Column '{$table->name}.{$fkCol}' looks like a foreign key but has no index. Add an index for query performance.",
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Generate a comprehensive security report for the given schema context.
     */
    public function generateReport(SchemaContextDTO $context): SecurityReportDTO
    {
        $permissionFindings = $this->analyzePermissions($context);
        $piiFindings = $this->detectPIIExposure($context);
        $constraintIssues = $this->auditConstraints($context);
        $score = $this->calculateScore($permissionFindings, $piiFindings, $constraintIssues);
        $recommendations = $this->buildRecommendations($permissionFindings, $piiFindings, $constraintIssues);

        return new SecurityReportDTO(
            score: $score,
            permissionFindings: $permissionFindings,
            piiFindings: $piiFindings,
            constraintIssues: $constraintIssues,
            recommendations: $recommendations,
            metadata: [
                'total_tables' => count($context->tables),
                'total_columns' => array_sum(array_map(fn (TableDTO $t) => count($t->columns), $context->tables)),
                'total_permission_findings' => count($permissionFindings),
                'total_pii_findings' => count($piiFindings),
                'total_constraint_issues' => count($constraintIssues),
            ],
        );
    }

    // ─── Private Helpers ─────────────────────────────────────────────

    private function systemPrompt(): string
    {
        return 'You are AetherDB AI, specialized in database security analysis. Identify permission issues, PII exposure, and constraint weaknesses. Always respond in valid JSON format only.';
    }

    private function buildPrompt(SchemaContextDTO $context): string
    {
        $tablesJson = json_encode(array_map(fn (TableDTO $t) => [
            'name' => $t->name,
            'columns' => array_map(fn (ColumnDTO $c) => [
                'name' => $c->name,
                'type' => $c->type,
                'nullable' => $c->nullable,
                'primary' => $c->primary,
                'comment' => $c->comment,
            ], $t->columns),
        ], $context->tables), JSON_PRETTY_PRINT);

        return <<<PROMPT
Database: {$context->database}
Schema: {$tablesJson}

Task: Perform a security audit on this database schema. Focus on:
1. Over-privileged accounts and permission structure
2. PII-sensitive columns (email, phone, SSN, password, credit card, etc.)
3. Constraint coverage (NOT NULL, UNIQUE, primary keys)

Respond ONLY with JSON:
{
  "findings": [{"severity": "high|medium|low", "message": "..."}],
  "recommendations": [{"priority": "high|medium|low", "message": "..."}],
  "score": 0-100,
  "report": {
    "permission_issues": [...],
    "pii_exposure": [...],
    "constraint_issues": [...]
  }
}
PROMPT;
    }

    private function parseResponse(string $response): array
    {
        $data = json_decode($response, true);

        if ($data) {
            return $data;
        }

        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $response, $m)) {
            $data = json_decode($m[1], true);
            if ($data) {
                return $data;
            }
        }

        if (preg_match('/\{[^{}]*\}/s', $response, $m)) {
            $data = json_decode($m[0], true);
            if ($data) {
                return $data;
            }
        }

        return [
            'findings' => [['severity' => 'low', 'message' => 'AI: '.mb_substr($response, 0, 150)]],
            'recommendations' => [['priority' => 'low', 'message' => 'AI security analysis raw response shown above.']],
            'score' => 0,
            'report' => [],
        ];
    }

    /**
     * Match a column name against known PII patterns.
     *
     * @return array{category: string, severity: string}|null
     */
    private function matchPiiPattern(string $columnName): ?array
    {
        foreach (self::PII_PATTERNS as $category => $config) {
            if (preg_match($config['pattern'], $columnName)) {
                return [
                    'category' => $category,
                    'severity' => $config['severity'],
                ];
            }
        }

        return null;
    }

    /**
     * Check if a column has a UNIQUE index.
     */
    private function hasUniqueIndex(TableDTO $table, string $columnName): bool
    {
        foreach ($table->indexes as $index) {
            if ($index->unique && in_array($columnName, $index->columns, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate overall security score (0–100) based on findings.
     */
    private function calculateScore(
        array $permissionFindings,
        array $piiFindings,
        array $constraintIssues,
    ): int {
        $score = 100;

        $allFindings = array_merge(
            $permissionFindings,
            $piiFindings,
            array_map(fn ($i) => new PermissionFindingDTO(
                user: 'N/A',
                role: 'N/A',
                severity: $i['severity'],
                issue: $i['message'],
                recommendation: 'N/A',
            ), $constraintIssues),
        );

        foreach ($allFindings as $finding) {
            $severity = $finding instanceof PermissionFindingDTO || $finding instanceof PIIFindingDTO
                ? $finding->severity
                : ($finding['severity'] ?? 'LOW');

            $score -= match ($severity) {
                'HIGH' => 15,
                'MEDIUM' => 8,
                default => 3,
            };
        }

        return max(0, min(100, $score));
    }

    /**
     * Build actionable recommendations from all findings.
     *
     * @return array [['priority' => '...', 'message' => '...']]
     */
    private function buildRecommendations(
        array $permissionFindings,
        array $piiFindings,
        array $constraintIssues,
    ): array {
        $recs = [];

        // Group by severity for high-level recommendations
        $highCount = 0;
        $mediumCount = 0;

        foreach ($permissionFindings as $f) {
            $f->severity === 'HIGH' ? $highCount++ : ($f->severity === 'MEDIUM' ? $mediumCount++ : null);
        }
        foreach ($piiFindings as $f) {
            $f->severity === 'HIGH' ? $highCount++ : ($f->severity === 'MEDIUM' ? $mediumCount++ : null);
        }
        foreach ($constraintIssues as $i) {
            ($i['severity'] ?? 'LOW') === 'HIGH' ? $highCount++ : (($i['severity'] ?? 'LOW') === 'MEDIUM' ? $mediumCount++ : null);
        }

        if ($highCount > 0) {
            $recs[] = [
                'priority' => 'HIGH',
                'message' => "Resolve {$highCount} HIGH severity issues immediately. These represent critical security or data integrity risks.",
            ];
        }

        if ($mediumCount > 0) {
            $recs[] = [
                'priority' => 'MEDIUM',
                'message' => "Address {$mediumCount} MEDIUM severity issues in the next sprint to improve overall security posture.",
            ];
        }

        // Add specific recommendations from PII findings (unique ones)
        $seenRecs = [];
        foreach ($piiFindings as $f) {
            $key = md5($f->recommendation);
            if (! isset($seenRecs[$key]) && $f->severity === 'HIGH') {
                $seenRecs[$key] = true;
                $recs[] = [
                    'priority' => $f->severity,
                    'message' => $f->recommendation,
                ];
            }
        }

        // If nothing found, give a positive recommendation
        if (empty($recs)) {
            $recs[] = [
                'priority' => 'LOW',
                'message' => 'Schema security posture looks good. Continue monitoring with regular audits.',
            ];
        }

        return $recs;
    }

    private function percent(int $part, int $total): string
    {
        return $total > 0 ? round(($part / $total) * 100).'%' : '0%';
    }
}
