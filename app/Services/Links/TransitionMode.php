<?php

namespace App\Services\Links;

enum TransitionMode: string
{
    case Direct = 'direct';
    case Delayed = 'delayed';
    case Manual = 'manual';

    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }

    public function usesPage(): bool
    {
        return $this === self::Delayed || $this === self::Manual;
    }
}
