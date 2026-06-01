<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Prompts;

use App\Modules\Schema\DTOs\SchemaContextDTO;

/**
 * DocumentationPrompt — builds the AI prompt for generating database documentation
 */
class DocumentationPrompt
{
    public function build(SchemaContextDTO $context): string
    {
        $tableCount = $context->summary['total_tables'] ?? count($context->tables);

        return <<<PROMPT
You are AetherDB AI, a database documentation specialist.

You have been given the following database schema:

Database: {$context->database}
Tables: {$tableCount}

Schema JSON:
{$context->toJson()}

Your task: Generate comprehensive documentation for this database. For each table, provide:

1. **table_description**: A human-readable description of what this table stores (1-2 sentences)
2. **domain**: Business domain this table belongs to (e.g., auth, commerce, finance, content, system)
3. **column_descriptions**: For each column, provide:
   - A clear description of what the column stores
   - Tags (e.g., ["identifier", "timestamp", "enum", "foreign_key", "monetary"])
   - An example value appropriate for the column type
4. **relationships**: Document foreign key relationships with name, from_table, from_column, to_table, to_column, type
5. **tags**: Relevant business tags for the table (e.g., ["core", "audit", "config", "reference"])

Respond ONLY with valid JSON in this exact structure:
{
  "tables": [
    {
      "name": "table_name",
      "description": "Table description",
      "domain": "auth",
      "columns": [
        {
          "name": "column_name",
          "description": "Column description",
          "tags": ["identifier"],
          "example_value": "1"
        }
      ],
      "relationships": [
        {
          "name": "fk_name",
          "from_table": "table_a",
          "from_column": "user_id",
          "to_table": "users",
          "to_column": "id",
          "type": "belongs_to"
        }
      ],
      "tags": ["core"]
    }
  ]
}
PROMPT;
    }
}
