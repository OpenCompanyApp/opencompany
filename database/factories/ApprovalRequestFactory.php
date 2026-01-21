<?php

namespace Database\Factories;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApprovalRequest>
 */
class ApprovalRequestFactory extends Factory
{
    protected $model = ApprovalRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'apr' . fake()->unique()->randomNumber(5),
            'type' => fake()->randomElement(['budget', 'action', 'spawn', 'access']),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'requester_id' => User::factory(),
            'amount' => null,
            'status' => 'pending',
            'responded_by_id' => null,
            'responded_at' => null,
        ];
    }

    /**
     * Create a pending approval.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'responded_by_id' => null,
            'responded_at' => null,
        ]);
    }

    /**
     * Create an approved request.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'responded_by_id' => User::factory(),
            'responded_at' => now(),
        ]);
    }

    /**
     * Create a rejected request.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'responded_by_id' => User::factory(),
            'responded_at' => now(),
        ]);
    }

    /**
     * Create a budget request.
     */
    public function budget(float $amount = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'budget',
            'amount' => $amount ?? fake()->randomFloat(2, 100, 5000),
        ]);
    }

    /**
     * Create an action request.
     */
    public function action(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'action',
        ]);
    }

    /**
     * Create a spawn request.
     */
    public function spawn(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'spawn',
        ]);
    }

    /**
     * Create an access request.
     */
    public function access(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'access',
        ]);
    }
}
