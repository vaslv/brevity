<?php

namespace App\Services\Links\CodeStrategy;

use App\Models\Link;

interface CodeGenerator
{
    /**
     * Generate code value for the given Link.
     */
    public function generate(Link $link): string;
}
