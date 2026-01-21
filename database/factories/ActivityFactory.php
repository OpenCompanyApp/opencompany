<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'act' . fake()->unique()->randomNumber(5),
            'type' => fake()->randomElement(['message', 'task_completed', 'task_started', 'agent_spawned', 'approval_needed', 'approval_granted', 'error']),
            'description' => fake()->sentence(),
            'actor_id' => User::factory(),
            'metadata' => [],
            'timestamp' => now(),
        ];
    }

    /**
     * Create a task completion activity.
     */
    public function taskCompleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'task_completed',
            'description' => 'Completed task: ' . fake()->sentence(3),
        ]);
    }

    /**
     * Create a message activity.
     */
    public function message(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'message',
            'description' => 'Sent a message in channel',
        ]);
    }

    /**
     * Create a task started activity.
     */
    public function taskStarted(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'task_started',
            'description' => 'Started task: ' . fake()->sentence(3),
        ]);
    }

    /**
     * Create an approval needed activity.
     */
    public function approvalNeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'approval_needed',
            'description' => 'Requested approval for: ' . fake()->sentence(3),
        ]);
    }

    /**
     * Create an agent spawn activity.
     */
    public function agentSpawned(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'agent_spawned',
            'description' => 'Spawned new agent: ' . fake()->word(),
        ]);
    }
}
