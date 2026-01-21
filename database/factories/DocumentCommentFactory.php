<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentComment>
 */
class DocumentCommentFactory extends Factory
{
    protected $model = DocumentComment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'docc' . fake()->unique()->randomNumber(5),
            'document_id' => Document::factory(),
            'author_id' => User::factory(),
            'content' => fake()->paragraph(),
            'parent_id' => null,
            'resolved' => false,
            'resolved_by_id' => null,
            'resolved_at' => null,
        ];
    }

    /**
     * Create a resolved comment.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'resolved' => true,
            'resolved_by_id' => User::factory(),
            'resolved_at' => now(),
        ]);
    }

    /**
     * Create a reply to another comment.
     */
    public function replyTo(DocumentComment $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
            'document_id' => $parent->document_id,
        ]);
    }
}
