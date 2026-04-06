<?php

namespace App\Services\Links;

enum TransitionMode: string
{
    public function usesPage(): bool
    {
        return $this === self::Delayed || $this === self::Manual;
    }

    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
    case Delayed = 'delayed';
    case Direct = 'direct';
    case Manual = 'manual';
}
