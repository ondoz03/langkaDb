<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Agents;

use App\Modules\AIAgent\Contracts\AgentInterface;
use App\Modules\AIAgent\DTOs\AgentResultDTO;
use App\Modules\AIAgent\DTOs\ColumnDocumentationDTO;
use App\Modules\AIAgent\DTOs\DocResultDTO;
use App\Modules\AIAgent\DTOs\TableDocumentationDTO;
use App\Modules\AIAgent\Prompts\DocumentationPrompt;
use App\Modules\AIAgent\Services\AIRouter;
use App\Modules\Schema\DTOs\ColumnDTO;
use App\Modules\Schema\DTOs\RelationDTO;
use App\Modules\Schema\DTOs\SchemaContextDTO;
use App\Modules\Schema\DTOs\TableDTO;

/**
 * DocumentationAgent — generates database documentation, data dictionary, and schema markdown
 *
 * Generates human-readable documentation for database schemas including:
 * - Data dictionaries with table and column descriptions
 * - Markdown schema documentation
 * - Business domain mapping reports
 * - Export to Markdown and JSON formats
 */
class DocumentationAgent implements AgentInterface
{
    public function __construct(
        private readonly AIRouter $router,
        private readonly DocumentationPrompt $prompt,
    ) {}

    /**
     * Implements AgentInterface: analyze the schema and generate documentation
     */
    public function analyze(SchemaContextDTO $context, ?string $apiKey = null, string $provider = 'rule'): AgentResultDTO
    {
        $dictionary = $this->generateDataDictionary($context, $apiKey, $provider);

        $findings = [];
        foreach ($dictionary->tables as $table) {
            $findings[] = [
                'severity' => 'info',
                'message' => "Table `{$table->name}` ({$table->domain}): {$table->description}",
            ];
        }

        return new AgentResultDTO(
            agent: 'documentation',
            findings: $findings,
            recommendations: [
                ['priority' => 'low', 'message' => 'Data dictionary generated with '.$dictionary->totalTables.' tables and '.$dictionary->totalColumns.' columns.'],
                ['priority' => 'low', 'message' => 'Export available in Markdown and JSON formats.'],
            ],
            score: $dictionary->totalTables > 0 ? 100 : 0,
            metadata: [
                'total_tables' => $dictionary->totalTables,
                'total_columns' => $dictionary->totalColumns,
                'total_relations' => $dictionary->totalRelations,
                'domains' => $dictionary->domains,
            ],
        );
    }

    /**
     * Generate a structured data dictionary with AI-generated descriptions
     */
    public function generateDataDictionary(
        SchemaContextDTO $context,
        ?string $apiKey = null,
        string $provider = 'rule',
    ): DocResultDTO {
        $aiResponse = $this->callAiForDocumentation($context, $apiKey, $provider);
        $parsed = $this->parseAiResponse($aiResponse);

        $tableDocs = [];
        $totalColumns = 0;
        $totalRelations = 0;
        $domains = [];

        foreach ($context->tables as $table) {
            $tableName = $table->name;
            $aiData = $parsed[$tableName] ?? [];

            $columnDocs = $this->buildColumnDocs($table, $aiData['columns'] ?? []);
            $totalColumns += count($columnDocs);

            $relationships = $this->buildRelationships($tableName, $context->relations);
            $totalRelations += count($relationships);

            $tableDocs[] = new TableDocumentationDTO(
                name: $tableName,
                columns: $columnDocs,
                description: $aiData['description'] ?? $this->inferTableDescription($table, $context),
                domain: $aiData['domain'] ?? $this->inferDomain($table),
                rowCount: $table->rowCount,
                sizeMb: $table->sizeMb,
                comment: $table->comment,
                relationships: $relationships,
                indexes: array_map(fn ($i) => $i->toArray(), $table->indexes),
                tags: $aiData['tags'] ?? [],
            );

            $domain = $aiData['domain'] ?? $this->inferDomain($table);
            if (! in_array($domain, $domains, true)) {
                $domains[] = $domain;
            }
        }

        return new DocResultDTO(
            database: $context->database,
            tables: $tableDocs,
            totalTables: count($tableDocs),
            totalColumns: $totalColumns,
            totalRelations: $totalRelations,
            domains: $domains,
            generatedAt: date('c'),
            metadata: [
                'provider' => $provider,
                'ai_enabled' => $apiKey !== null || $provider !== 'rule',
            ],
        );
    }

    /**
     * Generate schema documentation as a Markdown string
     */
    public function generateSchemaDocumentation(SchemaContextDTO $context, ?string $apiKey = null, string $provider = 'rule'): string
    {
        $dictionary = $this->generateDataDictionary($context, $apiKey, $provider);

        return $this->exportMarkdown($dictionary);
    }

    /**
     * Export the data dictionary to Markdown format
     */
    public function exportMarkdown(DocResultDTO $dictionary): string
    {
        return $dictionary->toMarkdown();
    }

    /**
     * Export the data dictionary to a JSON-serializable array
     */
    public function exportJson(DocResultDTO $dictionary): array
    {
        return $dictionary->toArray();
    }

    /**
     * Call the AI router for documentation generation
     */
    private function callAiForDocumentation(SchemaContextDTO $context, ?string $apiKey, string $provider): string
    {
        $promptText = $this->prompt->build($context);
        $systemPrompt = 'You are AetherDB AI, a database documentation specialist. Always respond in valid JSON format only.';

        return $this->router->route('documentation', $promptText, $systemPrompt, $apiKey, $provider);
    }

    /**
     * Parse the AI response into a structured array keyed by table name
     */
    private function parseAiResponse(string $response): array
    {
        $data = json_decode($response, true);

        if (! $data) {
            if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $response, $m)) {
                $data = json_decode($m[1], true);
            }
        }

        if (! $data) {
            if (preg_match('/\{[^{}]*\}/s', $response, $m)) {
                $data = json_decode($m[0], true);
            }
        }

        $tables = $data['tables'] ?? [];
        $result = [];

        foreach ($tables as $table) {
            $name = $table['name'] ?? '';
            if ($name) {
                $result[$name] = $table;
            }
        }

        return $result;
    }

    /**
     * Build ColumnDocumentationDTO array from a table's columns and AI data
     *
     * @param  ColumnDTO[]  $schemaColumns
     */
    private function buildColumnDocs(TableDTO $table, array $aiColumnData): array
    {
        $aiByColumn = [];
        foreach ($aiColumnData as $col) {
            $aiByColumn[$col['name'] ?? ''] = $col;
        }

        $docs = [];
        foreach ($table->columns as $col) {
            $ai = $aiByColumn[$col->name] ?? [];

            $docs[] = new ColumnDocumentationDTO(
                name: $col->name,
                type: $col->type,
                nullable: $col->nullable,
                default: $col->default,
                primary: $col->primary,
                comment: $col->comment,
                description: $ai['description'] ?? $this->inferColumnDescription($col->name, $col->type),
                tags: $ai['tags'] ?? [],
                exampleValue: $ai['example_value'] ?? null,
            );
        }

        return $docs;
    }

    /**
     * Build relationship data for a table from the schema's relation list
     */
    private function buildRelationships(string $tableName, array $relations): array
    {
        $result = [];

        foreach ($relations as $rel) {
            if ($rel instanceof RelationDTO) {
                if ($rel->fromTable === $tableName || $rel->toTable === $tableName) {
                    $result[] = $rel->toArray();
                }
            } elseif (is_array($rel)) {
                if (($rel['from_table'] ?? '') === $tableName || ($rel['to_table'] ?? '') === $tableName) {
                    $result[] = $rel;
                }
            }
        }

        return $result;
    }

    /**
     * Infer a human-readable table description from its columns and comment
     */
    private function inferTableDescription(TableDTO $table, SchemaContextDTO $context): string
    {
        if ($table->comment) {
            return $table->comment;
        }

        $colNames = array_map(fn ($c) => $c->name, $table->columns);

        $primaryKey = null;
        foreach ($table->columns as $col) {
            if ($col->primary) {
                $primaryKey = $col->name;
                break;
            }
        }

        $hasTimestamps = in_array('created_at', $colNames) || in_array('updated_at', $colNames);
        $hasDeleted = in_array('deleted_at', $colNames);
        $hasForeignKeys = in_array('user_id', $colNames) || in_array('id_'.$table->name, $colNames);

        $parts = ["Table `{$table->name}`"];
        if ($primaryKey) {
            $parts[] = "primary key: `{$primaryKey}`";
        }
        $parts[] = count($table->columns).' columns';
        if ($hasTimestamps) {
            $parts[] = 'with timestamps';
        }
        $parts[] = '~'.$table->rowCount.' rows';

        return implode(', ', $parts).'.';
    }

    /**
     * Infer a business domain from the table name and structure
     */
    private function inferDomain(TableDTO $table): string
    {
        $name = strtolower($table->name);

        $domainPatterns = [
            'auth|user|role|permission|session' => 'auth',
            'order|invoice|payment|cart|checkout|transaction' => 'commerce',
            'product|inventory|stock|warehouse|supplier|category' => 'catalog',
            'account|ledger|balance|budget|expense|revenue|finance' => 'finance',
            'post|article|comment|page|media|file|content' => 'content',
            'customer|contact|lead|deal|opportunity|account|crm' => 'crm',
            'log|audit|event|history|trace|monitor' => 'audit',
            'config|setting|preference|meta|flag|feature' => 'config',
            'notification|message|email|sms|alert' => 'notification',
            'team|member|subscription|plan|billing|license' => 'billing',
        ];

        foreach ($domainPatterns as $pattern => $domain) {
            if (preg_match("/{$pattern}/i", $name)) {
                return $domain;
            }
        }

        // Check column types as fallback
        foreach ($table->columns as $col) {
            if (in_array($col->name, ['price', 'amount', 'total', 'subtotal'], true)) {
                return 'commerce';
            }
            if (in_array($col->name, ['email', 'password', 'remember_token'], true)) {
                return 'auth';
            }
        }

        return 'general';
    }

    /**
     * Infer a column description from its name and type
     */
    private function inferColumnDescription(string $name, string $type): string
    {
        $name = strtolower($name);

        $descriptions = [
            'id' => 'Unique identifier for this record',
            'uuid' => 'Universally unique identifier for this record',
            'created_at' => 'Timestamp when this record was created',
            'updated_at' => 'Timestamp when this record was last updated',
            'deleted_at' => 'Timestamp when this record was soft-deleted',
            'email' => 'Email address',
            'password' => 'Hashed password',
            'remember_token' => 'Token for "remember me" authentication',
            'name' => 'Name',
            'slug' => 'URL-friendly identifier',
            'title' => 'Title or heading',
            'description' => 'Description or summary',
            'content' => 'Content body',
            'status' => 'Current status or state',
            'type' => 'Type or category classification',
            'is_active' => 'Whether this record is active',
            'is_enabled' => 'Whether this feature is enabled',
            'sort_order' => 'Sort order for ordering records',
            'position' => 'Position or sort order',
            'parent_id' => 'Reference to the parent record',
        ];

        foreach ($descriptions as $pattern => $desc) {
            if (str_contains($name, $pattern)) {
                return $desc;
            }
        }

        // Type-based fallback
        $typeLower = strtolower($type);
        if (str_contains($typeLower, 'int')) {
            return 'Numeric identifier or count';
        }
        if (str_contains($typeLower, 'varchar') || str_contains($typeLower, 'text') || str_contains($typeLower, 'char')) {
            return 'Text value';
        }
        if (str_contains($typeLower, 'decimal') || str_contains($typeLower, 'float') || str_contains($typeLower, 'double')) {
            return 'Numeric value';
        }
        if (str_contains($typeLower, 'bool') || str_contains($typeLower, 'tinyint')) {
            return 'Boolean flag';
        }
        if (str_contains($typeLower, 'date') || str_contains($typeLower, 'time')) {
            return 'Date or timestamp';
        }
        if (str_contains($typeLower, 'enum')) {
            return 'Enumeration value';
        }
        if (str_contains($typeLower, 'json')) {
            return 'JSON data';
        }

        return ucfirst(str_replace('_', ' ', $name));
    }
}
