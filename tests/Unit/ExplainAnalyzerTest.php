<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Connection\DTOs\ConnectionDTO;
use App\Modules\Connection\Models\Connection;
use App\Modules\Connection\Repositories\ConnectionRepository;
use App\Modules\Connection\Services\ConnectionEncryptor;
use App\Modules\Query\DTOs\ExplainNodeDTO;
use App\Modules\Query\DTOs\ExplainResultDTO;
use App\Modules\Query\Services\ExplainAnalyzer;

/**
 * Test the ExplainAnalyzer service with mocked DB dependencies.
 * Focuses on the parsing logic: formatTree(), getCostBreakdown(), suggestOptimizations().
 */
uses()->group('unit', 'query', 'explain');

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a partial mock of ExplainAnalyzer that skips the actual DB call.
 */
function makeAnalyzer(): ExplainAnalyzer
{
    $repo = mock(ConnectionRepository::class);
    $encryptor = mock(ConnectionEncryptor::class);

    return new ExplainAnalyzer($repo, $encryptor);
}

/**
 * Sample EXPLAIN FORMAT=JSON for a simple query: SELECT * FROM users WHERE id = 1.
 */
function simpleExplainJson(): array
{
    return [
        'query_block' => [
            'select_id' => 1,
            'cost_info' => [
                'query_cost' => '1.00',
            ],
            'table' => [
                'table_name' => 'users',
                'access_type' => 'const',
                'rows_examined_per_scan' => 1,
                'rows_produced_per_join' => 1,
                'filtered' => '100.00',
                'cost_info' => [
                    'read_cost' => '0.00',
                    'eval_cost' => '0.00',
                    'prefix_cost' => '1.00',
                    'data_read_per_join' => '48',
                ],
                'used_columns' => ['id', 'name', 'email'],
                'key' => 'PRIMARY',
                'used_key_parts' => ['id'],
                'key_length' => '8',
                'ref' => ['const'],
            ],
        ],
    ];
}

/**
 * Sample EXPLAIN FORMAT=JSON for a JOIN with full table scan.
 */
function joinExplainJson(): array
{
    return [
        'query_block' => [
            'select_id' => 1,
            'cost_info' => [
                'query_cost' => '1042.50',
            ],
            'nested_loop' => [
                [
                    'table' => [
                        'table_name' => 'orders',
                        'access_type' => 'ALL',
                        'rows_examined_per_scan' => 50000,
                        'rows_produced_per_join' => 50000,
                        'filtered' => '100.00',
                        'cost_info' => [
                            'read_cost' => '1000.00',
                            'eval_cost' => '0.50',
                            'prefix_cost' => '1040.00',
                            'data_read_per_join' => '2M',
                        ],
                        'used_columns' => ['id', 'user_id', 'total'],
                        'possible_keys' => ['idx_user_id'],
                    ],
                ],
                [
                    'table' => [
                        'table_name' => 'users',
                        'access_type' => 'eq_ref',
                        'rows_examined_per_scan' => 1,
                        'rows_produced_per_join' => 1,
                        'filtered' => '100.00',
                        'cost_info' => [
                            'read_cost' => '1.00',
                            'eval_cost' => '0.00',
                            'prefix_cost' => '1042.50',
                            'data_read_per_join' => '96',
                        ],
                        'used_columns' => ['id', 'name'],
                        'key' => 'PRIMARY',
                        'used_key_parts' => ['id'],
                        'key_length' => '8',
                        'ref' => ['aetherdb.orders.user_id'],
                    ],
                ],
            ],
        ],
    ];
}

/**
 * Sample EXPLAIN with Using filesort and Using temporary.
 */
function filesortExplainJson(): array
{
    return [
        'query_block' => [
            'select_id' => 1,
            'cost_info' => [
                'query_cost' => '500.00',
            ],
            'table' => [
                'table_name' => 'users',
                'access_type' => 'ALL',
                'rows_examined_per_scan' => 10000,
                'rows_produced_per_join' => 10000,
                'filtered' => '10.00',
                'cost_info' => [
                    'read_cost' => '400.00',
                    'eval_cost' => '100.00',
                    'prefix_cost' => '500.00',
                    'data_read_per_join' => '1M',
                ],
                'used_columns' => ['id', 'name', 'email', 'created_at'],
                'using_temporary' => true,
                'using_filesort' => true,
            ],
        ],
    ];
}

/**
 * Sample EXPLAIN for a subquery.
 */
function subqueryExplainJson(): array
{
    return [
        'query_block' => [
            'select_id' => 1,
            'cost_info' => [
                'query_cost' => '100.00',
            ],
            'table' => [
                'table_name' => 'users',
                'access_type' => 'ALL',
                'rows_examined_per_scan' => 10000,
                'rows_produced_per_join' => 10000,
                'filtered' => '100.00',
                'cost_info' => [
                    'read_cost' => '50.00',
                    'eval_cost' => '50.00',
                    'prefix_cost' => '100.00',
                    'data_read_per_join' => '1M',
                ],
                'used_columns' => ['id', 'name'],
                'attached_subqueries' => [
                    [
                        'select_id' => 2,
                        'cost_info' => [
                            'query_cost' => '0.50',
                        ],
                        'table' => [
                            'table_name' => 'profiles',
                            'access_type' => 'ref',
                            'rows_examined_per_scan' => 1,
                            'rows_produced_per_join' => 1,
                            'filtered' => '100.00',
                            'cost_info' => [
                                'read_cost' => '0.50',
                                'eval_cost' => '0.00',
                                'prefix_cost' => '0.50',
                                'data_read_per_join' => '48',
                            ],
                            'used_columns' => ['id', 'user_id', 'bio'],
                            'key' => 'idx_user_id',
                            'used_key_parts' => ['user_id'],
                            'key_length' => '8',
                            'ref' => ['aetherdb.users.id'],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

/**
 * Sample EXPLAIN with a nested_loop nesting (double nested loop).
 */
function nestedNestedLoopExplainJson(): array
{
    return [
        'query_block' => [
            'select_id' => 1,
            'cost_info' => [
                'query_cost' => '5000.00',
            ],
            'nested_loop' => [
                [
                    'nested_loop' => [
                        [
                            'table' => [
                                'table_name' => 'a',
                                'access_type' => 'ALL',
                                'rows_examined_per_scan' => 1000,
                                'rows_produced_per_join' => 1000,
                                'filtered' => '100.00',
                                'cost_info' => [
                                    'read_cost' => '100.00',
                                    'eval_cost' => '10.00',
                                    'prefix_cost' => '110.00',
                                ],
                                'used_columns' => ['id'],
                            ],
                        ],
                        [
                            'table' => [
                                'table_name' => 'b',
                                'access_type' => 'ref',
                                'rows_examined_per_scan' => 10,
                                'rows_produced_per_join' => 10,
                                'filtered' => '50.00',
                                'cost_info' => [
                                    'read_cost' => '200.00',
                                    'eval_cost' => '5.00',
                                    'prefix_cost' => '315.00',
                                ],
                                'used_columns' => ['id', 'a_id'],
                                'key' => 'idx_a_id',
                            ],
                        ],
                    ],
                ],
                [
                    'table' => [
                        'table_name' => 'c',
                        'access_type' => 'eq_ref',
                        'rows_examined_per_scan' => 1,
                        'rows_produced_per_join' => 1,
                        'filtered' => '100.00',
                        'cost_info' => [
                            'read_cost' => '1.00',
                            'eval_cost' => '0.00',
                            'prefix_cost' => '5000.00',
                        ],
                        'used_columns' => ['id'],
                        'key' => 'PRIMARY',
                    ],
                ],
            ],
        ],
    ];
}

// ---------------------------------------------------------------------------
// Tests: formatTree()
// ---------------------------------------------------------------------------

test('formatTree parses simple const lookup', function () {
    $analyzer = makeAnalyzer();
    $tree = $analyzer->formatTree(simpleExplainJson());

    expect($tree)->toHaveCount(1);

    $root = $tree[0];
    expect($root->type)->toBe('query_block');
    expect($root->children)->toHaveCount(1);

    $table = $root->children[0];
    expect($table->type)->toBe('table')
        ->and($table->table)->toBe('users')
        ->and($table->accessType)->toBe('CONST')
        ->and($table->cost)->toBe(1.0)
        ->and($table->rows)->toBe(1)
        ->and($table->key)->toBe('PRIMARY')
        ->and($table->filtered)->toBe(100.0);
});

test('formatTree parses JOIN with nested_loop', function () {
    $analyzer = makeAnalyzer();
    $tree = $analyzer->formatTree(joinExplainJson());

    expect($tree)->toHaveCount(1);

    $root = $tree[0];
    expect($root->type)->toBe('query_block');
    expect($root->children)->toHaveCount(2);

    // First table: orders (ALL — full table scan)
    $orders = $root->children[0];
    expect($orders->table)->toBe('orders')
        ->and($orders->accessType)->toBe('ALL')
        ->and($orders->rows)->toBe(50000);

    // Second table: users (eq_ref)
    $users = $root->children[1];
    expect($users->table)->toBe('users')
        ->and($users->accessType)->toBe('EQ_REF')
        ->and($users->key)->toBe('PRIMARY');
});

test('formatTree handles nested nested_loop (complex joins)', function () {
    $analyzer = makeAnalyzer();
    $tree = $analyzer->formatTree(nestedNestedLoopExplainJson());

    expect($tree)->toHaveCount(1);
    $root = $tree[0];
    expect($root->children)->toHaveCount(2);

    // First child: the nested_loop items get parsed as individual tables
    // since parseNestedLoopItem handles each nested_loop array entry
    $first = $root->children[0];
    expect($first->type)->toBe('nested_loop');
    expect($first->children)->toHaveCount(2);

    // Second child: c (eq_ref)
    $second = $root->children[1];
    expect($second->table)->toBe('c');
});

test('formatTree parses query with filesort and temporary', function () {
    $analyzer = makeAnalyzer();
    $tree = $analyzer->formatTree(filesortExplainJson());

    expect($tree)->toHaveCount(1);

    $table = $tree[0]->children[0];
    expect($table->accessType)->toBe('ALL')
        ->and($table->extra)->toContain('Using filesort')
        ->and($table->extra)->toContain('Using temporary');
});

test('formatTree returns empty array for empty input', function () {
    $analyzer = makeAnalyzer();
    $tree = $analyzer->formatTree([]);

    expect($tree)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Tests: getCostBreakdown()
// ---------------------------------------------------------------------------

test('getCostBreakdown returns correct total for simple query', function () {
    $analyzer = makeAnalyzer();
    $breakdown = $analyzer->getCostBreakdown(simpleExplainJson());

    expect($breakdown['total_query_cost'])->toBe(1.0)
        ->and($breakdown['tables'])->toHaveCount(1)
        ->and($breakdown['tables'][0]['table'])->toBe('users')
        ->and($breakdown['tables'][0]['access_type'])->toBe('CONST');
});

test('getCostBreakdown extracts per-table costs for joins', function () {
    $analyzer = makeAnalyzer();
    $breakdown = $analyzer->getCostBreakdown(joinExplainJson());

    expect($breakdown['total_query_cost'])->toBe(1042.50)
        ->and($breakdown['tables'])->toHaveCount(2);

    expect($breakdown['tables'][0]['table'])->toBe('orders')
        ->and($breakdown['tables'][0]['cost']['prefix_cost'])->toBe(1040.0);

    expect($breakdown['tables'][1]['table'])->toBe('users')
        ->and($breakdown['tables'][1]['cost']['prefix_cost'])->toBe(1042.50);
});

test('getCostBreakdown lists operations', function () {
    $analyzer = makeAnalyzer();
    $breakdown = $analyzer->getCostBreakdown(joinExplainJson());

    expect($breakdown['operations'])->not->toBeEmpty();

    $ops = $breakdown['operations'];
    expect($ops[0]['type'])->toBe('nested_loop')
        ->and($ops[0]['table'])->toBe('orders');
});

// ---------------------------------------------------------------------------
// Tests: suggestOptimizations()
// ---------------------------------------------------------------------------

test('suggestOptimizations warns about full table scan', function () {
    $analyzer = makeAnalyzer();
    $tree = $analyzer->formatTree(joinExplainJson());
    $cost = $analyzer->getCostBreakdown(joinExplainJson());

    $result = new ExplainResultDTO(
        query: 'SELECT * FROM orders JOIN users ON orders.user_id = users.id',
        tree: $tree,
        costBreakdown: $cost,
        suggestions: [],
        raw: joinExplainJson(),
    );

    $suggestions = $analyzer->suggestOptimizations($result);

    expect($suggestions)->toContain('Full table scan on `orders` — add index or optimize WHERE clause.');
});

test('suggestOptimizations warns about filesort and temporary', function () {
    $analyzer = makeAnalyzer();
    $tree = $analyzer->formatTree(filesortExplainJson());
    $cost = $analyzer->getCostBreakdown(filesortExplainJson());

    $result = new ExplainResultDTO(
        query: 'SELECT * FROM users ORDER BY name',
        tree: $tree,
        costBreakdown: $cost,
        suggestions: [],
        raw: filesortExplainJson(),
    );

    $suggestions = $analyzer->suggestOptimizations($result);

    expect($suggestions)->toContain('Using filesort on `users` — add index on ORDER BY columns.')
        ->and($suggestions)->toContain('Using temporary table — optimize GROUP BY or DISTINCT with appropriate indexes.');
});

test('suggestOptimizations warns about high query cost', function () {
    $analyzer = makeAnalyzer();
    $tree = $analyzer->formatTree(joinExplainJson());
    $cost = $analyzer->getCostBreakdown(joinExplainJson());

    // Total cost is 1042.50, which is > 1000, so it should warn
    $result = new ExplainResultDTO(
        query: 'SELECT * FROM orders JOIN users ...',
        tree: $tree,
        costBreakdown: $cost,
        suggestions: [],
        raw: joinExplainJson(),
    );

    $suggestions = $analyzer->suggestOptimizations($result);

    $hasHighCostWarning = false;

    foreach ($suggestions as $s) {
        if (str_contains($s, 'High query cost')) {
            $hasHighCostWarning = true;
            break;
        }
    }

    expect($hasHighCostWarning)->toBeTrue();
});

test('suggestOptimizations returns no suggestions for optimal query', function () {
    $analyzer = makeAnalyzer();
    $tree = $analyzer->formatTree(simpleExplainJson());
    $cost = $analyzer->getCostBreakdown(simpleExplainJson());

    $result = new ExplainResultDTO(
        query: 'SELECT * FROM users WHERE id = 1',
        tree: $tree,
        costBreakdown: $cost,
        suggestions: [],
        raw: simpleExplainJson(),
    );

    $suggestions = $analyzer->suggestOptimizations($result);

    // A simple const lookup with low cost should have no specific suggestions
    // (it may have the high-cost warning if cost > 1000, which is not the case here)
    expect($suggestions)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Tests: ExplainNodeDTO
// ---------------------------------------------------------------------------

test('ExplainNodeDTO can be created and serialized', function () {
    $child = new ExplainNodeDTO(
        id: 'table_1.0',
        type: 'table',
        table: 'users',
        cost: 1.0,
        rows: 1,
        filtered: 100.0,
        accessType: 'const',
        key: 'PRIMARY',
        extra: null,
        costInfo: ['read_cost' => '0.00'],
        usedColumns: ['id', 'name'],
        children: [],
        extraFields: ['select_id' => 1],
    );

    $arr = $child->toArray();

    expect($arr['id'])->toBe('table_1.0')
        ->and($arr['table'])->toBe('users')
        ->and($arr['access_type'])->toBe('const')
        ->and($arr['cost'])->toBe(1.0);
});

test('ExplainNodeDTO can be deserialized from array', function () {
    $data = [
        'id' => 'table_1.0',
        'type' => 'table',
        'table' => 'users',
        'cost' => 1.0,
        'rows' => 1,
        'filtered' => 100.0,
        'access_type' => 'const',
        'key' => 'PRIMARY',
        'extra' => null,
        'cost_info' => [],
        'used_columns' => [],
        'children' => [
            [
                'id' => 'table_1.0.0',
                'type' => 'table',
                'table' => 'profiles',
                'cost' => 0.5,
                'rows' => 1,
                'filtered' => 100.0,
                'access_type' => 'ref',
                'key' => null,
                'extra' => null,
                'cost_info' => [],
                'used_columns' => [],
                'children' => [],
                'extra_fields' => [],
            ],
        ],
        'extra_fields' => [],
    ];

    $node = ExplainNodeDTO::fromArray($data);

    expect($node->table)->toBe('users')
        ->and($node->children)->toHaveCount(1)
        ->and($node->children[0]->table)->toBe('profiles')
        ->and($node->children[0]->accessType)->toBe('ref');
});

// ---------------------------------------------------------------------------
// Tests: ExplainResultDTO
// ---------------------------------------------------------------------------

test('ExplainResultDTO can be created and serialized', function () {
    $tree = [
        new ExplainNodeDTO(
            id: 'query_block_1',
            type: 'query_block',
            table: 'users',
            cost: 1.0,
            rows: 1,
            filtered: 100.0,
            accessType: 'const',
            key: 'PRIMARY',
            extra: null,
        ),
    ];

    $result = new ExplainResultDTO(
        query: 'SELECT * FROM users WHERE id = 1',
        tree: $tree,
        costBreakdown: ['total_query_cost' => 1.0, 'tables' => []],
        suggestions: ['Add index'],
        raw: [],
    );

    $arr = $result->toArray();

    expect($arr['query'])->toBe('SELECT * FROM users WHERE id = 1')
        ->and($arr['tree'])->toHaveCount(1)
        ->and($arr['suggestions'])->toContain('Add index');
});

test('ExplainResultDTO can be deserialized from array', function () {
    $data = [
        'query' => 'SELECT 1',
        'tree' => [
            [
                'id' => 'query_block_1',
                'type' => 'query_block',
                'table' => null,
                'cost' => 0.0,
                'rows' => 0,
                'filtered' => 100.0,
                'access_type' => 'const',
                'key' => null,
                'extra' => null,
                'cost_info' => [],
                'used_columns' => [],
                'children' => [],
                'extra_fields' => [],
            ],
        ],
        'cost_breakdown' => ['total_query_cost' => 0.0],
        'suggestions' => [],
    ];

    $result = ExplainResultDTO::fromArray($data);

    expect($result->query)->toBe('SELECT 1')
        ->and($result->tree)->toHaveCount(1);
});
