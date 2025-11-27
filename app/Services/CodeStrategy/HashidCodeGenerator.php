<?php

namespace App\Services\CodeStrategy;

use App\Models\Link;
use Hashids\Hashids;

readonly class HashidCodeGenerator implements CodeGenerator
{
    public function __construct(private Hashids $hashids)
    {
    }

    public function generate(Link $link): string
    {
        return $this->hashids->encode($link->id);
    }
}

