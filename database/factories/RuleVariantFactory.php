<?php

namespace Database\Factories;

use App\Models\Rule;
use App\Models\RuleVariant;
use App\Models\Url;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuleVariant>
 */
class RuleVariantFactory extends Factory
{
    protected $model = RuleVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rule_id' => Rule::factory(),
            'url_id' => Url::factory(),
            'weight' => 1,
            'label' => null,
        ];
    }
}
