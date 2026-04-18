<?php

namespace App\Enums;

enum PackageManager: string
{
    case NPM = 'npm';
    case PNPM = 'pnpm';
    case BUN = 'bun';
    case YARN = 'yarn';

    public function installCommand(): string
    {
        return "{$this->value} install";
    }

    public function addDevCommand(array $packages): string
    {
        $packagesStr = implode(' ', $packages);

        return match ($this) {
            self::YARN => "yarn add --dev {$packagesStr}",
            self::PNPM => "pnpm add --save-dev {$packagesStr}",
            self::BUN => "bun add --dev {$packagesStr}",
            default => "npm install --save-dev {$packagesStr}",
        };
    }

    public function buildCommand(): string
    {
        return match ($this) {
            self::YARN => 'yarn build',
            default => "{$this->value} run build",
        };
    }

    public function devCommand(): string
    {
        return match ($this) {
            self::YARN, self::PNPM => "{$this->value} dev",
            default => "{$this->value} run dev",
        };
    }

    public function laravelInstallFlag(): string
    {
        return "--{$this->value}";
    }
}
