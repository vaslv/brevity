<?php

namespace App\Models;

use App\Models\Relations\BelongsToDomain;
use App\Models\Relations\BelongsToService;
use App\Models\Relations\HasManyClicks;
use App\Models\Relations\HasManyRules;
use App\Services\CodeStrategy\CodeGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use League\Uri\Uri;

class Link extends Model
{
    use BelongsToDomain;
    use BelongsToService;
    use HasManyClicks;
    use HasManyRules;
    use SoftDeletes;

    public const UPDATED_AT = null;

    protected $casts = [
        'forward_query' => 'boolean',
        'callback_data' => 'array',
        'deleted_at' => 'datetime',
    ];

    protected $fillable = [
        'service_id',
        'domain_id',
        'code',
        'title',
        'forward_query',
        'callback_data',
    ];

    protected static function booted(): void
    {
        static::created(function (Link $link) {
            /** @var CodeGenerator $generator */
            $generator = app(CodeGenerator::class);

            $link->updateQuietly([
                'code' => $generator->generate($link),
            ]);
        });
    }

    public function getUrlAttribute(): string
    {
        $base = $this->domain?->url ?? config('app.url');

        return Uri::new($base)
            ->withPath('/'.$this->code)
            ->toString();
    }
}
