<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'task' . fake()->unique()->randomNumber(5),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['backlog', 'in_progress', 'done']),
            'assignee_id' => User::factory(),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'cost' => fake()->randomFloat(2, 0, 100),
            'estimated_cost' => fake()->randomFloat(2, 0, 200),
            'channel_id' => null,
            'position' => fake()->numberBetween(0, 100),
            'completed_at' => null,
            'workspace_id' => fn () => app()->bound('currentWorkspace') ? app('currentWorkspace')->id : null,
        ];
    }

    /**
     * Create a backlog task.
     */
    public function backlog(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'backlog',
        ]);
    }


    /**
     * Create an in-progress task.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
        ]);
    }

    /**
     * Create a completed task.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'done',
            'completed_at' => now(),
        ]);
    }

    /**
     * Create a high priority task.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }

    /**
     * Create an urgent task.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'urgent',
        ]);
    }
}
