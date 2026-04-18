<?php

namespace App\Enums;

enum ScoutDriver: string
{
    case MEILISEARCH = 'meilisearch';
    case TYPESENSE = 'typesense';
    case DATABASE = 'database';

    public function label(): string
    {
        return match ($this) {
            self::MEILISEARCH => 'Meilisearch (Self-hosted)',
            self::TYPESENSE => 'Typesense (Self-hosted)',
            self::DATABASE => 'Database (No extra infrastructure)',
        };
    }

    public function port(): int
    {
        return match ($this) {
            self::MEILISEARCH => 7700,
            self::TYPESENSE => 8108,
            default => 80,
        };
    }

    public function envDriver(): string
    {
        return $this->value;
    }
}
