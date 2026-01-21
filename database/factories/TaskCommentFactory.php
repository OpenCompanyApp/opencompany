<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskComment>
 */
class TaskCommentFactory extends Factory
{
    protected $model = TaskComment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'tc' . fake()->unique()->randomNumber(5),
            'task_id' => Task::factory(),
            'author_id' => User::factory(),
            'content' => fake()->paragraph(),
            'parent_id' => null,
        ];
    }

    /**
     * Create a reply to another comment.
     */
    public function replyTo(TaskComment $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
            'task_id' => $parent->task_id,
        ]);
    }
}
