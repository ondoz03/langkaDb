<?php

declare(strict_types=1);

use App\Modules\AIAgent\Agents\OptimizationAgent;
use App\Modules\Schema\DTOs\ColumnDTO;
use App\Modules\Schema\DTOs\IndexDTO;
use App\Modules\Schema\DTOs\SchemaContextDTO;
use App\Modules\Schema\DTOs\TableDTO;

beforeEach(function () {
    $this->agent = new OptimizationAgent;
});

// ─── Schema Factory Helpers ─────────────────────────────────────────────

function makeWellIndexedTable(): TableDTO
{
    return new TableDTO(
        name: 'users',
        columns: [
            new ColumnDTO(name: 'id', type: 'bigint unsigned', nullable: false, default: null, primary: true, comment: null),
            new ColumnDTO(name: 'name', type: 'varchar(255)', nullable: false, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'email', type: 'varchar(255)', nullable: false, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'role_id', type: 'bigint unsigned', nullable: false, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'company_id', type: 'bigint unsigned', nullable: true, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'created_at', type: 'timestamp', nullable: true, default: null, primary: false, comment: null),
        ],
        indexes: [
            new IndexDTO(name: 'PRIMARY', columns: ['id'], unique: true, type: 'primary'),
            new IndexDTO(name: 'users_email_unique', columns: ['email'], unique: true, type: 'unique'),
            new IndexDTO(name: 'idx_role_id', columns: ['role_id'], unique: false, type: 'index'),
            new IndexDTO(name: 'idx_company_id', columns: ['company_id'], unique: false, type: 'index'),
        ],
        rowCount: 1500,
        sizeMb: 0.8,
        comment: null,
    );
}

function makeTableWithoutPk(): TableDTO
{
    return new TableDTO(
        name: 'logs',
        columns: [
            new ColumnDTO(name: 'message', type: 'text', nullable: false, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'level', type: 'varchar(20)', nullable: false, default: 'info', primary: false, comment: null),
            new ColumnDTO(name: 'created_at', type: 'timestamp', nullable: true, default: null, primary: false, comment: null),
        ],
        indexes: [],
        rowCount: 50000,
        sizeMb: 250.0,
        comment: null,
    );
}

function makeLargeTableNoIndex(): TableDTO
{
    return new TableDTO(
        name: 'events',
        columns: [
            new ColumnDTO(name: 'id', type: 'bigint unsigned', nullable: false, default: null, primary: true, comment: null),
            new ColumnDTO(name: 'type', type: 'varchar(50)', nullable: false, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'user_id', type: 'bigint unsigned', nullable: false, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'payload', type: 'json', nullable: true, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'created_at', type: 'timestamp', nullable: true, default: null, primary: false, comment: null),
        ],
        indexes: [
            new IndexDTO(name: 'PRIMARY', columns: ['id'], unique: true, type: 'primary'),
        ],
        rowCount: 100000,
        sizeMb: 500.0,
        comment: null,
    );
}

function makeTableWithDuplicateIndexes(): TableDTO
{
    return new TableDTO(
        name: 'orders',
        columns: [
            new ColumnDTO(name: 'id', type: 'bigint unsigned', nullable: false, default: null, primary: true, comment: null),
            new ColumnDTO(name: 'user_id', type: 'bigint unsigned', nullable: false, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'status', type: 'varchar(20)', nullable: false, default: 'pending', primary: false, comment: null),
            new ColumnDTO(name: 'total', type: 'decimal(10,2)', nullable: false, default: '0.00', primary: false, comment: null),
            new ColumnDTO(name: 'created_at', type: 'timestamp', nullable: true, default: null, primary: false, comment: null),
        ],
        indexes: [
            new IndexDTO(name: 'PRIMARY', columns: ['id'], unique: true, type: 'primary'),
            new IndexDTO(name: 'idx_user_id', columns: ['user_id'], unique: false, type: 'index'),
            new IndexDTO(name: 'idx_user_id_status', columns: ['user_id', 'status'], unique: false, type: 'index'),
        ],
        rowCount: 10000,
        sizeMb: 50.0,
        comment: null,
    );
}

function makeOverIndexedTable(): TableDTO
{
    return new TableDTO(
        name: 'tiny_config',
        columns: [
            new ColumnDTO(name: 'id', type: 'bigint unsigned', nullable: false, default: null, primary: true, comment: null),
            new ColumnDTO(name: 'key', type: 'varchar(100)', nullable: false, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'value', type: 'varchar(500)', nullable: true, default: null, primary: false, comment: null),
        ],
        indexes: [
            new IndexDTO(name: 'PRIMARY', columns: ['id'], unique: true, type: 'primary'),
            new IndexDTO(name: 'idx_key', columns: ['key'], unique: false, type: 'index'),
            new IndexDTO(name: 'idx_key_value', columns: ['key', 'value'], unique: false, type: 'index'),
            new IndexDTO(name: 'idx_value', columns: ['value'], unique: false, type: 'index'),
            new IndexDTO(name: 'idx_id_key', columns: ['id', 'key'], unique: false, type: 'index'),
            new IndexDTO(name: 'idx_key_id', columns: ['key', 'id'], unique: false, type: 'index'),
            new IndexDTO(name: 'idx_id_value', columns: ['id', 'value'], unique: false, type: 'index'),
        ],
        rowCount: 100,
        sizeMb: 0.1,
        comment: null,
    );
}

function makeWideVarcharTable(): TableDTO
{
    return new TableDTO(
        name: 'articles',
        columns: [
            new ColumnDTO(name: 'id', type: 'bigint unsigned', nullable: false, default: null, primary: true, comment: null),
            new ColumnDTO(name: 'title', type: 'varchar(255)', nullable: false, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'summary', type: 'varchar(600)', nullable: true, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'body', type: 'text', nullable: true, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'created_at', type: 'timestamp', nullable: true, default: null, primary: false, comment: null),
        ],
        indexes: [
            new IndexDTO(name: 'PRIMARY', columns: ['id'], unique: true, type: 'primary'),
        ],
        rowCount: 500,
        sizeMb: 2.0,
        comment: null,
    );
}

// ─── Tests ─────────────────────────────────────────────────────────────

test('analyze returns AgentResultDTO with agent name optimization', function () {
    $context = new SchemaContextDTO(
        database: 'test',
        tables: [makeWellIndexedTable()],
        relations: [],
        summary: ['total_tables' => 1],
    );

    $result = $this->agent->analyze($context);

    expect($result->agent)->toBe('optimization');
    expect($result->findings)->toBeArray();
    expect($result->recommendations)->toBeArray();
    expect($result->score)->toBeBetween(0, 100);
});

test('detects tables without primary key', function () {
    $context = new SchemaContextDTO(
        database: 'test',
        tables: [makeTableWithoutPk()],
        relations: [],
        summary: ['total_tables' => 1],
    );

    $result = $this->agent->analyze($context);

    $pkFindings = array_filter($result->findings, fn ($f) => str_contains($f['message'], 'No primary key'));
    expect($pkFindings)->not->toBeEmpty();
    expect($result->score)->toBeLessThan(100);
});

test('detects high row count tables without indexes', function () {
    $context = new SchemaContextDTO(
        database: 'test',
        tables: [
            makeWellIndexedTable(),
            new TableDTO(
                name: 'big_unindexed',
                columns: [
                    new ColumnDTO(name: 'id', type: 'bigint unsigned', nullable: false, default: null, primary: true, comment: null),
                    new ColumnDTO(name: 'data', type: 'text', nullable: true, default: null, primary: false, comment: null),
                ],
                indexes: [],
                rowCount: 20000,
                sizeMb: 100.0,
                comment: null,
            ),
        ],
        relations: [],
        summary: ['total_tables' => 2],
    );

    $result = $this->agent->analyze($context);

    $highRowFindings = array_filter($result->findings, fn ($f) => str_contains($f['message'], 'zero indexes'));
    expect($highRowFindings)->not->toBeEmpty();
});

test('detects missing foreign key indexes', function () {
    $table = new TableDTO(
        name: 'posts',
        columns: [
            new ColumnDTO(name: 'id', type: 'bigint unsigned', nullable: false, default: null, primary: true, comment: null),
            new ColumnDTO(name: 'user_id', type: 'bigint unsigned', nullable: false, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'category_id', type: 'bigint unsigned', nullable: true, default: null, primary: false, comment: null),
            new ColumnDTO(name: 'title', type: 'varchar(255)', nullable: false, default: null, primary: false, comment: null),
        ],
        indexes: [
            new IndexDTO(name: 'PRIMARY', columns: ['id'], unique: true, type: 'primary'),
        ],
        rowCount: 5000,
        sizeMb: 10.0,
        comment: null,
    );

    $context = new SchemaContextDTO(
        database: 'test',
        tables: [$table],
        relations: [],
        summary: ['total_tables' => 1],
    );

    $result = $this->agent->analyze($context);

    $fkFindings = array_filter($result->findings, fn ($f) => str_contains($f['message'], 'Foreign key column'));
    expect($fkFindings)->toHaveCount(2);

    $userIds = array_filter($fkFindings, fn ($f) => str_contains($f['message'], 'user_id'));
    $categoryIds = array_filter($fkFindings, fn ($f) => str_contains($f['message'], 'category_id'));
    expect($userIds)->not->toBeEmpty();
    expect($categoryIds)->not->toBeEmpty();
});

test('detects duplicate/redundant indexes', function () {
    $context = new SchemaContextDTO(
        database: 'test',
        tables: [makeTableWithDuplicateIndexes()],
        relations: [],
        summary: ['total_tables' => 1],
    );

    $result = $this->agent->analyze($context);

    $dupFindings = array_filter($result->findings, fn ($f) => str_contains($f['message'], 'Duplicate'));
    expect($dupFindings)->not->toBeEmpty();
});

test('detects large tables with only primary key', function () {
    $context = new SchemaContextDTO(
        database: 'test',
        tables: [makeLargeTableNoIndex()],
        relations: [],
        summary: ['total_tables' => 1],
    );

    $result = $this->agent->analyze($context);

    $largeTableFindings = array_filter($result->findings, fn ($f) => str_contains($f['message'], 'Large table'));
    expect($largeTableFindings)->not->toBeEmpty();
});

test('detects over-indexed small tables', function () {
    $context = new SchemaContextDTO(
        database: 'test',
        tables: [makeOverIndexedTable()],
        relations: [],
        summary: ['total_tables' => 1],
    );

    $result = $this->agent->analyze($context);

    $overIndexedFindings = array_filter($result->findings, fn ($f) => str_contains($f['message'], 'Over-indexed'));
    expect($overIndexedFindings)->not->toBeEmpty();
});

test('detects wide VARCHAR columns', function () {
    $context = new SchemaContextDTO(
        database: 'test',
        tables: [makeWideVarcharTable()],
        relations: [],
        summary: ['total_tables' => 1],
    );

    $result = $this->agent->analyze($context);

    $wideVarcharFindings = array_filter($result->findings, fn ($f) => str_contains($f['message'], 'VARCHAR'));
    expect($wideVarcharFindings)->not->toBeEmpty();
});

test('well-indexed schema gets high score', function () {
    $context = new SchemaContextDTO(
        database: 'test',
        tables: [makeWellIndexedTable()],
        relations: [],
        summary: ['total_tables' => 1],
    );

    $result = $this->agent->analyze($context);

    expect($result->score)->toBeGreaterThanOrEqual(70);
});

test('bad schema gets low score', function () {
    $context = new SchemaContextDTO(
        database: 'test',
        tables: [makeTableWithoutPk(), makeLargeTableNoIndex()],
        relations: [],
        summary: ['total_tables' => 2],
    );

    $result = $this->agent->analyze($context);

    expect($result->score)->toBeLessThanOrEqual(60);
});

test('metadata contains expected keys', function () {
    $context = new SchemaContextDTO(
        database: 'test',
        tables: [makeWellIndexedTable(), makeTableWithoutPk()],
        relations: [],
        summary: ['total_tables' => 2],
    );

    $result = $this->agent->analyze($context);

    expect($result->metadata)->toHaveKeys(['total_tables', 'total_indexes', 'tables_without_pk', 'high_rows_no_index', 'source']);
    expect($result->metadata['total_tables'])->toBe(2);
    expect($result->metadata['source'])->toBe('rule-based');
});
