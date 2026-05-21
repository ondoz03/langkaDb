<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Contracts;

use App\Modules\AIAgent\DTOs\AgentResultDTO;
use App\Modules\Schema\DTOs\SchemaContextDTO;

interface AgentInterface
{
    public function analyze(SchemaContextDTO $context, ?string $apiKey = null, string $provider = 'rule'): AgentResultDTO;
}
