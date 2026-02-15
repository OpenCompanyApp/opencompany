<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'doc' . fake()->unique()->randomNumber(5),
            'title' => fake()->sentence(3),
            'content' => "# " . fake()->sentence(3) . "\n\n" . fake()->paragraphs(3, true),
            'author_id' => User::factory(),
            'parent_id' => null,
            'is_folder' => false,
            'workspace_id' => fn () => app()->bound('currentWorkspace') ? app('currentWorkspace')->id : null,
        ];
    }

    /**
     * Create a folder.
     */
    public function folder(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_folder' => true,
            'content' => null,
        ]);
    }

    /**
     * Create a child document.
     */
    public function childOf(Document $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
        ]);
    }

    /**
     * Create a document with markdown content.
     */
    public function withMarkdown(string $content = null): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => $content ?? "# Sample Document\n\n## Introduction\n\nThis is a sample document with **bold** and *italic* text.\n\n- List item 1\n- List item 2\n\n```javascript\nconsole.log('Hello World');\n```",
        ]);
    }
}
