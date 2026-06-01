<?php

declare(strict_types=1);

use App\Modules\AIAgent\Agents\SecurityAgent;
use App\Modules\AIAgent\Services\AIRouter;
use App\Modules\AIAgent\Services\RuleBasedService;
use App\Modules\Schema\DTOs\ColumnDTO;
use App\Modules\Schema\DTOs\IndexDTO;
use App\Modules\Schema\DTOs\SchemaContextDTO;
use App\Modules\Schema\DTOs\TableDTO;

beforeEach(function () {
    $ruleBased = new RuleBasedService;
    $router = new AIRouter($ruleBased);
    $this->agent = new SecurityAgent($router);
});

// ─── Schema Factory Helpers ─────────────────────────────────────────────

/**
 * Build a users table with known PII columns and privilege flags.
 */
function makeUsersTable(): TableDTO
{
    return new TableDTO(
        name: 'users',
        columns: [
            new ColumnDTO(name: 'id', type: 'bigint unsigned', nullable: false, default: null, primary: true, comment: null),
            new ColumnDTO(name: 'name', type: 'varchar(255)', nullable: false, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'email', type: 'varchar(255)', nullable: false, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'password', type: 'varchar(255)', nullable: false, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'phone', type: 'varchar(20)', nullable: true, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'address', type: 'text', nullable: true, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'is_admin', type: 'tinyint(1)', nullable: false, default: '0', primary: false, comment: null),
            new ColumnDTO(name: 'api_token', type: 'varchar(80)', nullable: true, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'created_at', type: 'timestamp', nullable: true, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'updated_at', type: 'timestamp', nullable: true, default: null, primary: false, comment: null),
        ],
        indexes: [
            new IndexDTO(name: 'PRIMARY', columns: ['id'], unique: true, type: 'primary'),
            new IndexDTO(name: 'users_email_unique', columns: ['email'], unique: true, type: 'unique'),
        ],
        rowCount: 1500,
        sizeMb: 0.8,
        comment: null,
    );
}

/**
 * Build a roles table.
 */
function makeRolesTable(): TableDTO
{
    return new TableDTO(
        name: 'roles',
        columns: [
            new ColumnDTO(name: 'id', type: 'bigint unsigned', nullable: false, default: null, primary: true, comment: null),
            new ColumnDTO(name: 'name', type: 'varchar(50)', nullable: false, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'guard_name', type: 'varchar(50)', nullable: false, default: null, primary: false, comment: null),
        ],
        indexes: [
            new IndexDTO(name: 'PRIMARY', columns: ['id'], unique: true, type: 'primary'),
            new IndexDTO(name: 'roles_name_unique', columns: ['name'], unique: true, type: 'unique'),
        ],
        rowCount: 5,
        sizeMb: 0.01,
        comment: null,
    );
}

/**
 * Build a user_roles pivot table.
 */
function makeUserRolesTable(): TableDTO
{
    return new TableDTO(
        name: 'user_roles',
        columns: [
            new ColumnDTO(name: 'user_id', type: 'bigint unsigned', nullable: false, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'role_id', type: 'bigint unsigned', nullable: false, default: null, primary: false, comment: null),
        ],
        indexes: [
            new IndexDTO(name: 'user_roles_user_id_index', columns: ['user_id'], unique: false, type: 'index'),
            new IndexDTO(name: 'user_roles_role_id_index', columns: ['role_id'], unique: false, type: 'index'),
        ],
        rowCount: 0,
        sizeMb: 0.01,
        comment: 'Pivot table for user-role associations',
    );
}

/**
 * Build a problematic table — no primary key, too many nullable columns, missing FK index.
 */
function makeLogTable(): TableDTO
{
    return new TableDTO(
        name: 'audit_log',
        columns: [
            new ColumnDTO(name: 'id', type: 'int', nullable: true, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'user_id', type: 'int', nullable: true, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'action', type: 'varchar(100)', nullable: true, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'description', type: 'text', nullable: true, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'ip_address', type: 'varchar(45)', nullable: true, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'created_at', type: 'timestamp', nullable: true, default: null, primary: false, comment: null),
        ],
        indexes: [],
        rowCount: 50000,
        sizeMb: 12.5,
        comment: null,
    );
}

/**
 * Build a table with SSN and credit card data (PII red flag).
 */
function makePaymentTable(): TableDTO
{
    return new TableDTO(
        name: 'payments',
        columns: [
            new ColumnDTO(name: 'id', type: 'bigint unsigned', nullable: false, default: null, primary: true, comment: null),
            new ColumnDTO(name: 'user_id', type: 'bigint unsigned', nullable: false, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'ssn', type: 'varchar(11)', nullable: true, default: null, primary: false, comment: 'Social security number'),
            new ColumnDTO(name: 'credit_card', type: 'varchar(16)', nullable: true, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'amount', type: 'decimal(10,2)', nullable: false, default: null, primary: false, comment: null),
        ],
        indexes: [
            new IndexDTO(name: 'PRIMARY', columns: ['id'], unique: true, type: 'primary'),
        ],
        rowCount: 300,
        sizeMb: 0.1,
        comment: null,
    );
}

// ─── Tests ──────────────────────────────────────────────────────────────

describe('SecurityAgent', function () {

    describe('analyzePermissions', function () {

        it('detects over-privilege via inline is_admin column', function () {
            $context = new SchemaContextDTO(
                database: 'test_db',
                tables: [makeUsersTable(), makeRolesTable(), makeUserRolesTable()],
                relations: [],
                summary: ['total_tables' => 3, 'total_columns' => 15],
            );

            $findings = $this->agent->analyzePermissions($context);

            $adminFindings = array_values(array_filter(
                $findings,
                fn ($f) => str_contains($f->issue, 'is_admin'),
            ));

            expect($adminFindings)->not->toBeEmpty();
            expect($adminFindings[0]->severity)->toBe('HIGH');
        });

        it('flags missing roles table when users exist', function () {
            $context = new SchemaContextDTO(
                database: 'test_db',
                tables: [makeUsersTable()],
                relations: [],
                summary: ['total_tables' => 1, 'total_columns' => 10],
            );

            $findings = $this->agent->analyzePermissions($context);

            $roleFindings = array_values(array_filter(
                $findings,
                fn ($f) => str_contains($f->issue, 'no dedicated roles table'),
            ));

            expect($roleFindings)->not->toBeEmpty();
            expect($roleFindings[0]->severity)->toBe('MEDIUM');
        });

        it('flags missing pivot when users and roles both exist', function () {
            $context = new SchemaContextDTO(
                database: 'test_db',
                tables: [makeUsersTable(), makeRolesTable()],
                relations: [],
                summary: ['total_tables' => 2, 'total_columns' => 13],
            );

            $findings = $this->agent->analyzePermissions($context);

            $pivotFindings = array_values(array_filter(
                $findings,
                fn ($f) => str_contains($f->issue, 'no pivot'),
            ));

            expect($pivotFindings)->not->toBeEmpty();
            expect($pivotFindings[0]->severity)->toBe('MEDIUM');
        });

        it('flags missing user tables as a security concern', function () {
            $context = new SchemaContextDTO(
                database: 'empty_db',
                tables: [
                    new TableDTO('logs', [], [], 0, 0.0, null),
                    new TableDTO('sessions', [], [], 0, 0.0, null),
                ],
                relations: [],
                summary: ['total_tables' => 2, 'total_columns' => 0],
            );

            $findings = $this->agent->analyzePermissions($context);
            $messages = array_map(fn ($f) => $f->issue, $findings);

            expect($findings)->not->toBeEmpty();
            expect($messages)->toContain('No user/authentication tables detected in schema. Access control may be unstructured.');
        });
    });

    describe('detectPIIExposure', function () {

        it('detects email column as PII', function () {
            $context = new SchemaContextDTO(
                database: 'test_db',
                tables: [makeUsersTable()],
                relations: [],
                summary: ['total_tables' => 1, 'total_columns' => 10],
            );

            $findings = $this->agent->detectPIIExposure($context);

            $emailFindings = array_values(array_filter(
                $findings,
                fn ($f) => $f->category === 'email',
            ));

            expect($emailFindings)->not->toBeEmpty();
            expect($emailFindings[0]->table)->toBe('users');
            expect($emailFindings[0]->column)->toBe('email');
            expect($emailFindings[0]->severity)->toBe('MEDIUM');
        });

        it('detects password column as HIGH severity', function () {
            $context = new SchemaContextDTO(
                database: 'test_db',
                tables: [makeUsersTable()],
                relations: [],
                summary: ['total_tables' => 1, 'total_columns' => 10],
            );

            $findings = $this->agent->detectPIIExposure($context);

            $passwordFindings = array_values(array_filter(
                $findings,
                fn ($f) => $f->category === 'password',
            ));

            expect($passwordFindings)->not->toBeEmpty();
            expect($passwordFindings[0]->column)->toBe('password');
            expect($passwordFindings[0]->severity)->toBe('HIGH');
        });

        it('detects SSN and credit card in payments table', function () {
            $context = new SchemaContextDTO(
                database: 'test_db',
                tables: [makePaymentTable()],
                relations: [],
                summary: ['total_tables' => 1, 'total_columns' => 5],
            );

            $findings = $this->agent->detectPIIExposure($context);

            $categories = array_map(fn ($f) => $f->category, $findings);

            expect($categories)->toContain('ssn');
            expect($categories)->toContain('credit_card');
        });

        it('correctly identifies email as constrained (UNIQUE index exists)', function () {
            $context = new SchemaContextDTO(
                database: 'test_db',
                tables: [makeUsersTable()],
                relations: [],
                summary: ['total_tables' => 1, 'total_columns' => 10],
            );

            $findings = $this->agent->detectPIIExposure($context);

            $emailFinding = current(array_filter(
                $findings,
                fn ($f) => $f->category === 'email',
            ));

            expect($emailFinding)->not->toBeNull();
            expect($emailFinding->hasConstraint)->toBeTrue();
        });
    });

    describe('auditConstraints', function () {

        it('detects missing primary key', function () {
            $context = new SchemaContextDTO(
                database: 'test_db',
                tables: [makeLogTable()],
                relations: [],
                summary: ['total_tables' => 1, 'total_columns' => 6],
            );

            $issues = $this->agent->auditConstraints($context);

            $pkIssues = array_values(array_filter(
                $issues,
                fn ($i) => str_contains($i['message'], 'no primary key'),
            ));

            expect($pkIssues)->not->toBeEmpty();
            expect($pkIssues[0]['severity'])->toBe('HIGH');
        });

        it('detects missing index on foreign key column', function () {
            $context = new SchemaContextDTO(
                database: 'test_db',
                tables: [makePaymentTable()],
                relations: [],
                summary: ['total_tables' => 1, 'total_columns' => 5],
            );

            $issues = $this->agent->auditConstraints($context);

            $fkIssues = array_values(array_filter(
                $issues,
                fn ($i) => str_contains($i['message'], 'looks like a foreign key'),
            ));

            expect($fkIssues)->not->toBeEmpty();
            expect($fkIssues[0]['severity'])->toBe('MEDIUM');
            expect($fkIssues[0]['message'])->toContain('payments.user_id');
        });

        it('detects excessive nullable columns', function () {
            $context = new SchemaContextDTO(
                database: 'test_db',
                tables: [makeLogTable()],
                relations: [],
                summary: ['total_tables' => 1, 'total_columns' => 6],
            );

            $issues = $this->agent->auditConstraints($context);

            $nullableIssues = array_values(array_filter(
                $issues,
                fn ($i) => str_contains($i['message'], 'nullable'),
            ));

            expect($nullableIssues)->not->toBeEmpty();
            expect($nullableIssues[0]['message'])->toContain('audit_log');
        });
    });

    describe('generateReport', function () {

        it('returns a complete SecurityReportDTO with correct structure', function () {
            $context = new SchemaContextDTO(
                database: 'test_db',
                tables: [makeUsersTable(), makeRolesTable(), makeUserRolesTable(), makeLogTable(), makePaymentTable()],
                relations: [],
                summary: ['total_tables' => 5, 'total_columns' => 28],
            );

            $report = $this->agent->generateReport($context);

            expect($report->score)->toBeInt();
            expect($report->score)->toBeGreaterThanOrEqual(0);
            expect($report->score)->toBeLessThanOrEqual(100);
            expect($report->permissionFindings)->not->toBeEmpty();
            expect($report->piiFindings)->not->toBeEmpty();
            expect($report->constraintIssues)->not->toBeEmpty();
            expect($report->recommendations)->not->toBeEmpty();
            expect($report->metadata['total_tables'])->toBe(5);
        });

        it('generates high-severity recommendations for critical issues', function () {
            $context = new SchemaContextDTO(
                database: 'test_db',
                tables: [makePaymentTable(), makeLogTable()],
                relations: [],
                summary: ['total_tables' => 2, 'total_columns' => 11],
            );

            $report = $this->agent->generateReport($context);

            $highRecs = array_values(array_filter(
                $report->recommendations,
                fn ($r) => ($r['priority'] ?? '') === 'HIGH',
            ));

            expect($highRecs)->not->toBeEmpty();
        });
    });

    describe('analyze (AgentInterface)', function () {

        it('returns AgentResultDTO with rule-based analysis when no API key', function () {
            $context = new SchemaContextDTO(
                database: 'test_db',
                tables: [makeUsersTable(), makeRolesTable(), makePaymentTable()],
                relations: [],
                summary: ['total_tables' => 3, 'total_columns' => 18],
            );

            $result = $this->agent->analyze($context);

            expect($result->agent)->toBe('security');
            expect($result->findings)->not->toBeEmpty();
            expect($result->recommendations)->not->toBeEmpty();
            expect($result->score)->toBeGreaterThanOrEqual(0);
            expect($result->score)->toBeLessThanOrEqual(100);
            expect($result->metadata)->toHaveKey('security_report');
        });

        it('includes permission, PII, and constraint findings', function () {
            $context = new SchemaContextDTO(
                database: 'test_db',
                tables: [makeUsersTable(), makePaymentTable()],
                relations: [],
                summary: ['total_tables' => 2, 'total_columns' => 15],
            );

            $result = $this->agent->analyze($context);

            $categories = [];
            foreach ($result->findings as $f) {
                $prefix = explode(']', $f['message'])[0] . ']';
                $categories[$prefix] = true;
            }

            expect($categories)->toHaveKey('[Permission]');
            expect($categories)->toHaveKey('[PII]');
        });
    });

});
