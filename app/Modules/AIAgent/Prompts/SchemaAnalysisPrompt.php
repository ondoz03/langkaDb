<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Prompts;

use App\Modules\Schema\DTOs\SchemaContextDTO;

class SchemaAnalysisPrompt
{
    public function build(SchemaContextDTO $context): string
    {
        return <<<PROMPT
You are AetherDB AI, an expert database analyst.

You have been given the following database schema:

Database: {$context->database}
Tables: {$context->summary['total_tables']}

Schema JSON:
{$context->toJson()}

Your task: Analyze this schema and provide:
1. Domain cluster grouping for each table
2. Business domain inference
3. Relationship quality score
4. Top 3 structural issues

Respond in JSON format only.
PROMPT;
    }
}
