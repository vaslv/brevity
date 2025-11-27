<?php

namespace App\Models;

use App\Models\Relations\HasManyLinks;
use Illuminate\Database\Eloquent\Model;
use League\Uri\Uri;

class Domain extends Model
{
    use HasManyLinks;

    public const UPDATED_AT = null;

    protected $fillable = [
        'value',
    ];

    protected $appends = [
        'url'
    ];

    public function getUrlAttribute(): string
    {
        return Uri::new()
            ->withHost($this->value)
            ->withScheme('https')
            ->toString();
    }
}
