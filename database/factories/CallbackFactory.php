<?php

namespace Database\Factories;

use App\Models\Callback;
use App\Models\Click;
use App\Models\Service;
use App\Services\Links\Callbacks\CallbackStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Callback>
 */
class CallbackFactory extends Factory
{
    protected $model = Callback::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'click_id' => Click::factory(),
            'data' => ['x' => 'y'],
            'response_code' => null,
            'response_body' => null,
            'status' => CallbackStatus::Pending,
            'attempts' => 0,
            'last_attempt_at' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(['status' => CallbackStatus::Failed]);
    }

    public function sent(): static
    {
        return $this->state(['status' => CallbackStatus::Sent]);
    }
}
