<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'notif' . fake()->unique()->randomNumber(5),
            'type' => fake()->randomElement(['mention', 'task_assigned', 'approval_response', 'message']),
            'title' => fake()->sentence(4),
            'message' => fake()->sentence(),
            'user_id' => User::factory(),
            'is_read' => false,
            'action_url' => '/' . fake()->randomElement(['chat', 'tasks', 'docs', 'approvals']),
            'actor_id' => User::factory(),
            'metadata' => [],
        ];
    }

    /**
     * Create a read notification.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
        ]);
    }

    /**
     * Create an unread notification.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => false,
        ]);
    }

    /**
     * Create a mention notification.
     */
    public function mention(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'mention',
            'title' => 'You were mentioned',
        ]);
    }

    /**
     * Create a task assigned notification.
     */
    public function taskAssigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'task_assigned',
            'title' => 'Task assigned to you',
        ]);
    }

    /**
     * Create an approval response notification.
     */
    public function approvalResponse(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'approval_response',
            'title' => 'Approval request updated',
        ]);
    }
}
