<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\DTOs;

use Illuminate\Contracts\Support\Arrayable;

readonly class ChatMessageDTO implements Arrayable
{
    public function __construct(
        public string $role,
        public string $content,
        public ?string $timestamp = null,
    ) {}

    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
            'timestamp' => $this->timestamp ?? now()->toIso8601String(),
        ];
    }
}
