<?php

namespace App\Enums;

enum ObjectStorage: string
{
    case MINIO = 'MinIO (Classic)';
    case SEAWEEDFS = 'SeaweedFS (High Performance)';
    case GARAGE = 'Garage (Modern/Rust)';

    public function label(): string
    {
        return $this->value;
    }

    public function port(): int
    {
        return match ($this) {
            self::MINIO => 9000,
            self::SEAWEEDFS => 8333,
            self::GARAGE => 3900,
        };
    }

    public function consolePort(): int
    {
        return match ($this) {
            self::MINIO => 9001,
            self::SEAWEEDFS => 9333,
            self::GARAGE => 3902,
        };
    }

    public function defaultUser(): string
    {
        return 'minioadmin'; // Universal standard for local S3 stubs
    }

    public function defaultSecret(): string
    {
        return 'minioadmin';
    }
}
