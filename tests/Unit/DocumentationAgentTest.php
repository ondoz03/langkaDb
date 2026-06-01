<?php

declare(strict_types=1);

use App\Modules\AIAgent\Agents\DocumentationAgent;
use App\Modules\AIAgent\DTOs\DocResultDTO;
use App\Modules\AIAgent\DTOs\TableDocumentationDTO;
use App\Modules\AIAgent\DTOs\ColumnDocumentationDTO;
use App\Modules\AIAgent\Prompts\DocumentationPrompt;
use App\Modules\AIAgent\Services\AIRouter;
use App\Modules\Schema\DTOs\ColumnDTO;
use App\Modules\Schema\DTOs\IndexDTO;
use App\Modules\Schema\DTOs\RelationDTO;
use App\Modules\Schema\DTOs\SchemaContextDTO;
use App\Modules\Schema\DTOs\TableDTO;

// ---------------------------------------------------------------------------
// DTO Unit Tests
// ---------------------------------------------------------------------------

describe('ColumnDocumentationDTO', function () {
    it('can be created with constructor parameters', function () {
        $dto = new ColumnDocumentationDTO(
            name: 'email',
            type: 'varchar(255)',
            nullable: true,
            default: null,
            primary: false,
            comment: 'User email address',
            description: 'The user\'s email address used for login',
            tags: ['identifier', 'contact'],
            exampleValue: 'user@example.com',
        );

        expect($dto->name)->toBe('email');
        expect($dto->type)->toBe('varchar(255)');
        expect($dto->nullable)->toBeTrue();
        expect($dto->primary)->toBeFalse();
        expect($dto->description)->toBe("The user's email address used for login");
        expect($dto->tags)->toContain('identifier');
        expect($dto->exampleValue)->toBe('user@example.com');
    });

    it('can be created from array', function () {
        $dto = ColumnDocumentationDTO::fromArray([
            'name' => 'id',
            'type' => 'bigint unsigned',
            'nullable' => false,
            'default' => null,
            'primary' => true,
            'comment' => null,
            'description' => 'Primary key',
            'tags' => ['identifier'],
            'example_value' => '1',
        ]);

        expect($dto->name)->toBe('id');
        expect($dto->primary)->toBeTrue();
        expect($dto->nullable)->toBeFalse();
    });

    it('can be converted to array', function () {
        $dto = new ColumnDocumentationDTO(
            name: 'status',
            type: "enum('active','inactive')",
            nullable: false,
            default: 'active',
            primary: false,
            comment: null,
            description: 'Record status',
            tags: ['enum', 'status'],
            exampleValue: 'active',
        );

        $array = $dto->toArray();

        expect($array['name'])->toBe('status');
        expect($array['description'])->toBe('Record status');
        expect($array['default'])->toBe('active');
        expect($array['tags'])->toContain('enum');
        expect($array['example_value'])->toBe('active');
    });
});

describe('TableDocumentationDTO', function () {
    it('can be created with columns', function () {
        $columns = [
            new ColumnDocumentationDTO(
                name: 'id',
                type: 'bigint unsigned',
                nullable: false,
                default: null,
                primary: true,
                comment: null,
                description: 'Primary key',
            ),
            new ColumnDocumentationDTO(
                name: 'name',
                type: 'varchar(255)',
                nullable: false,
                default: null,
                primary: false,
                comment: null,
                description: 'User display name',
                exampleValue: 'John Doe',
            ),
        ];

        $dto = new TableDocumentationDTO(
            name: 'users',
            columns: $columns,
            description: 'Stores user accounts and authentication data',
            domain: 'auth',
            rowCount: 1500,
            sizeMb: 2.5,
            comment: 'Core user table',
            relationships: [
                ['name' => 'fk_user_role', 'from_table' => 'users', 'from_column' => 'role_id', 'to_table' => 'roles', 'to_column' => 'id', 'type' => 'belongs_to'],
            ],
            indexes: [
                ['name' => 'users_email_unique', 'columns' => ['email'], 'unique' => true, 'type' => 'btree'],
            ],
            tags: ['core', 'auth'],
        );

        expect($dto->name)->toBe('users');
        expect($dto->domain)->toBe('auth');
        expect($dto->columns)->toHaveCount(2);
        expect($dto->rowCount)->toBe(1500);
        expect($dto->relationships)->toHaveCount(1);
        expect($dto->tags)->toContain('core');
    });

    it('generates markdown table from columns', function () {
        $columns = [
            new ColumnDocumentationDTO(
                name: 'id', type: 'bigint unsigned', nullable: false,
                default: null, primary: true, comment: null,
                description: 'Primary key', tags: ['pk'],
            ),
            new ColumnDocumentationDTO(
                name: 'email', type: 'varchar(255)', nullable: false,
                default: null, primary: false, comment: null,
                description: 'Email address', exampleValue: 'test@test.com',
            ),
        ];

        $dto = new TableDocumentationDTO(
            name: 'users',
            columns: $columns,
            description: 'Users table',
            domain: 'auth',
            rowCount: 0,
            sizeMb: 0.0,
            comment: null,
        );

        $markdown = $dto->toMarkdownTable();
        expect($markdown)->toContain('| Column | Type | Flags | Description | Example |');
        expect($markdown)->toContain('| id | bigint unsigned | `PK` | Primary key |  |');
        expect($markdown)->toContain('| email | varchar(255) |  | Email address | e.g. `test@test.com` |');
    });

    it('can be serialized to and from array', function () {
        $original = new TableDocumentationDTO(
            name: 'orders',
            columns: [
                new ColumnDocumentationDTO(
                    name: 'total', type: 'decimal(10,2)', nullable: false,
                    default: '0.00', primary: false, comment: null,
                    description: 'Order total amount', tags: ['monetary'],
                ),
            ],
            description: 'Customer orders',
            domain: 'commerce',
            rowCount: 500,
            sizeMb: 1.2,
            comment: null,
        );

        $array = $original->toArray();
        $restored = TableDocumentationDTO::fromArray($array);

        expect($restored->name)->toBe('orders');
        expect($restored->domain)->toBe('commerce');
        expect($restored->columns[0]->name)->toBe('total');
        expect($restored->columns[0]->default)->toBe('0.00');
    });
});

describe('DocResultDTO', function () {
    it('can be created and converted to markdown', function () {
        $tables = [
            new TableDocumentationDTO(
                name: 'users',
                columns: [
                    new ColumnDocumentationDTO(
                        name: 'id', type: 'int', nullable: false,
                        default: null, primary: true, comment: null,
                        description: 'Primary key',
                    ),
                ],
                description: 'Stores user accounts',
                domain: 'auth',
                rowCount: 1000,
                sizeMb: 0.5,
                comment: null,
                relationships: [
                    ['name' => 'fk_role', 'from_table' => 'users', 'from_column' => 'role_id', 'to_table' => 'roles', 'to_column' => 'id', 'type' => 'belongs_to'],
                ],
                tags: ['core'],
            ),
        ];

        $dto = new DocResultDTO(
            database: 'test_db',
            tables: $tables,
            totalTables: 1,
            totalColumns: 1,
            totalRelations: 1,
            domains: ['auth'],
            generatedAt: '2026-01-01T00:00:00+00:00',
        );

        $markdown = $dto->toMarkdown();

        expect($markdown)->toContain('# Data Dictionary: test_db');
        expect($markdown)->toContain('## `users`');
        expect($markdown)->toContain('**Domain:** auth');
        expect($markdown)->toContain('| Column | Type | Flags | Description | Example |');
        expect($markdown)->toContain('### Relationships');
        expect($markdown)->toContain('**Tags:** `core`');
    });

    it('can be serialized to and from array', function () {
        $original = new DocResultDTO(
            database: 'myapp',
            tables: [
                new TableDocumentationDTO(
                    name: 'products',
                    columns: [],
                    description: 'Product catalog',
                    domain: 'catalog',
                    rowCount: 0,
                    sizeMb: 0.0,
                    comment: null,
                ),
            ],
            totalTables: 1,
            totalColumns: 0,
            totalRelations: 0,
            domains: ['catalog'],
            generatedAt: '2026-01-01T00:00:00+00:00',
        );

        $array = $original->toArray();
        $restored = DocResultDTO::fromArray($array);

        expect($restored->database)->toBe('myapp');
        expect($restored->tables[0]->name)->toBe('products');
        expect($restored->totalTables)->toBe(1);
    });
});

// ---------------------------------------------------------------------------
// DocumentationAgent Unit Tests
// ---------------------------------------------------------------------------

describe('DocumentationAgent', function () {
    function makeDocTestContext(): SchemaContextDTO
    {
        $tables = [
            new TableDTO(
                name: 'users',
                columns: [
                    new ColumnDTO('id', 'bigint unsigned', false, null, true, null),
                    new ColumnDTO('name', 'varchar(255)', false, null, false, null),
                    new ColumnDTO('email', 'varchar(255)', false, null, false, null),
                    new ColumnDTO('created_at', 'timestamp', true, null, false, null),
                    new ColumnDTO('updated_at', 'timestamp', true, null, false, null),
                ],
                indexes: [
                    new IndexDTO('primary', ['id'], true, 'btree'),
                    new IndexDTO('users_email_unique', ['email'], true, 'btree'),
                ],
                rowCount: 100,
                sizeMb: 0.5,
                comment: 'User accounts',
            ),
            new TableDTO(
                name: 'orders',
                columns: [
                    new ColumnDTO('id', 'bigint unsigned', false, null, true, null),
                    new ColumnDTO('user_id', 'bigint unsigned', false, null, false, null),
                    new ColumnDTO('total', 'decimal(10,2)', false, '0.00', false, null),
                    new ColumnDTO('status', "enum('pending','paid','shipped')", false, 'pending', false, null),
                    new ColumnDTO('created_at', 'timestamp', true, null, false, null),
                ],
                indexes: [
                    new IndexDTO('primary', ['id'], true, 'btree'),
                    new IndexDTO('orders_user_id_index', ['user_id'], false, 'btree'),
                ],
                rowCount: 500,
                sizeMb: 2.0,
                comment: null,
            ),
        ];

        $relations = [
            new RelationDTO('fk_orders_user', 'orders', 'user_id', 'users', 'id', 'belongs_to'),
        ];

        return new SchemaContextDTO(
            database: 'test_db',
            tables: $tables,
            relations: $relations,
            summary: ['total_tables' => 2, 'total_columns' => 9, 'total_relations' => 1],
        );
    }

    it('generates data dictionary with rule-based fallback', function () {
        $router = mock(AIRouter::class)
            ->shouldReceive('route')
            ->andReturn('{}')
            ->getMock();

        $prompt = new DocumentationPrompt();
        $agent = new DocumentationAgent($router, $prompt);

        $result = $agent->generateDataDictionary(makeDocTestContext());

        expect($result)->toBeInstanceOf(DocResultDTO::class);
        expect($result->database)->toBe('test_db');
        expect($result->tables)->toHaveCount(2);
        expect($result->totalTables)->toBe(2);
        expect($result->totalColumns)->toBeGreaterThan(0);
        expect($result->domains)->not->toBeEmpty();

        // Check users table was generated with fallback descriptions
        $usersTable = collect($result->tables)->firstWhere('name', 'users');
        expect($usersTable)->not->toBeNull();
        expect($usersTable->description)->toContain('User accounts');
        expect($usersTable->columns)->toHaveCount(5);
        expect($usersTable->relationships)->toHaveCount(1); // fk_orders_user targets users table
    });

    it('generates data dictionary with AI-parsed response', function () {
        $aiResponse = json_encode([
            'tables' => [
                [
                    'name' => 'users',
                    'description' => 'Stores registered user accounts and authentication data',
                    'domain' => 'auth',
                    'columns' => [
                        ['name' => 'id', 'description' => 'Auto-increment primary key', 'tags' => ['pk'], 'example_value' => '1'],
                        ['name' => 'email', 'description' => 'User login email address', 'tags' => ['identifier'], 'example_value' => 'user@example.com'],
                    ],
                    'tags' => ['core'],
                ],
                [
                    'name' => 'orders',
                    'description' => 'Customer purchase orders',
                    'domain' => 'commerce',
                    'columns' => [
                        ['name' => 'id', 'description' => 'Order ID', 'tags' => ['pk'], 'example_value' => '1001'],
                        ['name' => 'total', 'description' => 'Order monetary total', 'tags' => ['monetary'], 'example_value' => '99.99'],
                    ],
                    'tags' => ['financial'],
                ],
            ],
        ]);

        $router = mock(AIRouter::class)
            ->shouldReceive('route')
            ->andReturn($aiResponse)
            ->getMock();

        $prompt = new DocumentationPrompt();
        $agent = new DocumentationAgent($router, $prompt);

        $result = $agent->generateDataDictionary(makeDocTestContext(), 'test-key', 'openai');

        expect($result->database)->toBe('test_db');
        expect($result->totalTables)->toBe(2);

        // Users table should have AI-enhanced descriptions
        $usersTable = collect($result->tables)->firstWhere('name', 'users');
        expect($usersTable->description)->toBe('Stores registered user accounts and authentication data');
        expect($usersTable->domain)->toBe('auth');
        expect($usersTable->columns[0]->description)->toBe('Auto-increment primary key');
        expect($usersTable->columns[0]->exampleValue)->toBe('1');
    });

    it('implements AgentInterface and returns AgentResultDTO', function () {
        $router = mock(AIRouter::class)
            ->shouldReceive('route')
            ->andReturn('{}')
            ->getMock();

        $prompt = new DocumentationPrompt();
        $agent = new DocumentationAgent($router, $prompt);

        $result = $agent->analyze(makeDocTestContext());

        expect($result->agent)->toBe('documentation');
        expect($result->findings)->not->toBeEmpty();
        expect($result->score)->toBe(100);
        expect($result->metadata['total_tables'])->toBe(2);
    });

    it('generates schema documentation as markdown', function () {
        $router = mock(AIRouter::class)
            ->shouldReceive('route')
            ->andReturn('{}')
            ->getMock();

        $prompt = new DocumentationPrompt();
        $agent = new DocumentationAgent($router, $prompt);

        $markdown = $agent->generateSchemaDocumentation(makeDocTestContext());

        expect($markdown)->toBeString();
        expect($markdown)->toContain('# Data Dictionary: test_db');
        expect($markdown)->toContain('## `users`');
        expect($markdown)->toContain('## `orders`');
        expect($markdown)->toContain('| Column | Type | Flags | Description | Example |');
    });

    it('exports data dictionary as markdown', function () {
        $router = mock(AIRouter::class)
            ->shouldReceive('route')
            ->andReturn('{}')
            ->getMock();

        $prompt = new DocumentationPrompt();
        $agent = new DocumentationAgent($router, $prompt);

        $dictionary = $agent->generateDataDictionary(makeDocTestContext());
        $markdown = $agent->exportMarkdown($dictionary);

        expect($markdown)->toBeString();
        expect($markdown)->toContain('# Data Dictionary: test_db');
    });

    it('exports data dictionary as JSON array', function () {
        $router = mock(AIRouter::class)
            ->shouldReceive('route')
            ->andReturn('{}')
            ->getMock();

        $prompt = new DocumentationPrompt();
        $agent = new DocumentationAgent($router, $prompt);

        $dictionary = $agent->generateDataDictionary(makeDocTestContext());
        $json = $agent->exportJson($dictionary);

        expect($json)->toBeArray();
        expect($json['database'])->toBe('test_db');
        expect($json['tables'])->toHaveCount(2);
        expect($json['total_tables'])->toBe(2);
    });

    it('infers domains from table names', function () {
        $router = mock(AIRouter::class)
            ->shouldReceive('route')
            ->andReturn('{}')
            ->getMock();

        $prompt = new DocumentationPrompt();
        $agent = new DocumentationAgent($router, $prompt);

        $result = $agent->generateDataDictionary(makeDocTestContext());

        $users = collect($result->tables)->firstWhere('name', 'users');
        $orders = collect($result->tables)->firstWhere('name', 'orders');

        expect($users->domain)->toBe('auth');
        expect($orders->domain)->toBe('commerce');
    });
});

// ---------------------------------------------------------------------------
// DocumentationPrompt Unit Tests
// ---------------------------------------------------------------------------

describe('DocumentationPrompt', function () {
    it('builds a prompt string from schema context', function () {
        $context = makeDocTestContext();
        $prompt = new DocumentationPrompt();

        $result = $prompt->build($context);

        expect($result)->toBeString();
        expect($result)->toContain('test_db');
        expect($result)->toContain('Tables: 2');
        expect($result)->toContain('users');
        expect($result)->toContain('orders');
        expect($result)->toContain('Respond ONLY with valid JSON');
    });
});

// ---------------------------------------------------------------------------
// Domain Inference (private method tested via public contract)
// ---------------------------------------------------------------------------

describe('Domain inference', function () {
    it('maps auth-related tables to auth domain', function () {
        $router = mock(AIRouter::class)
            ->shouldReceive('route')
            ->andReturn('{}')
            ->getMock();

        $prompt = new DocumentationPrompt();
        $agent = new DocumentationAgent($router, $prompt);

        $tables = [
            new TableDTO('roles', [new ColumnDTO('id', 'int', false, null, true, null)], [], 0, 0.0, null),
            new TableDTO('sessions', [new ColumnDTO('id', 'int', false, null, true, null)], [], 0, 0.0, null),
        ];

        $context = new SchemaContextDTO('test', $tables, [], ['total_tables' => 2], );

        $result = $agent->generateDataDictionary($context);

        expect(collect($result->tables)->firstWhere('name', 'roles')->domain)->toBe('auth');
        expect(collect($result->tables)->firstWhere('name', 'sessions')->domain)->toBe('auth');
    });
});
