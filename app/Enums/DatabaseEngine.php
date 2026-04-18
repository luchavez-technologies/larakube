<?php

namespace App\Enums;

use App\Actions\DatabaseAction;
use App\Actions\MariaDbAction;
use App\Actions\MySqlAction;
use App\Actions\PostgresAction;
use App\Actions\RedisAction;

enum DatabaseEngine: string
{
    case SQLITE = 'SQLite';
    case MYSQL = 'MySQL';
    case MARIADB = 'MariaDB';
    case POSTGRESQL = 'PostgreSQL';
    case REDIS = 'Redis';

    public function action(): ?DatabaseAction
    {
        return match ($this) {
            self::MYSQL => new MySqlAction,
            self::MARIADB => new MariaDbAction,
            self::POSTGRESQL => new PostgresAction,
            self::REDIS => new RedisAction,
            default => null,
        };
    }

    public function dbConnection(): string
    {
        return match ($this) {
            self::MYSQL, self::MARIADB => 'mysql',
            self::POSTGRESQL => 'pgsql',
            default => 'sqlite',
        };
    }

    public function dbHost(): string
    {
        return match ($this) {
            self::MYSQL, self::MARIADB => 'mysql',
            self::POSTGRESQL => 'postgres',
            self::REDIS => 'redis',
            default => '127.0.0.1',
        };
    }

    public function dbPort(): int
    {
        return match ($this) {
            self::POSTGRESQL => 5432,
            self::REDIS => 6379,
            default => 3306,
        };
    }

    public function dbUsername(): string
    {
        return match ($this) {
            self::MYSQL, self::MARIADB => 'laravel',
            self::POSTGRESQL => 'postgres',
            default => 'root',
        };
    }

    public function isPersistent(): bool
    {
        return $this !== self::REDIS;
    }
}
