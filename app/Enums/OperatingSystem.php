<?php

namespace App\Enums;

enum OperatingSystem: string
{
    case DEBIAN = 'debian';
    case ALPINE = 'alpine';

    public function label(): string
    {
        return match ($this) {
            self::DEBIAN => 'Debian (Stable, widely compatible, larger image)',
            self::ALPINE => 'Alpine (Lightweight, smaller image, minimal footprint)',
        };
    }
}
