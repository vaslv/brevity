<?php

namespace App\Models;

use App\Models\Relations\HasManyLinks;
use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    use HasManyLinks;

    public const UPDATED_AT = null;

    protected $fillable = [
        'value',
    ];
}
