<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentVersion>
 */
class DocumentVersionFactory extends Factory
{
    protected $model = DocumentVersion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'docv' . fake()->unique()->randomNumber(5),
            'document_id' => Document::factory(),
            'title' => fake()->sentence(3),
            'content' => "# " . fake()->sentence(3) . "\n\n" . fake()->paragraphs(3, true),
            'author_id' => User::factory(),
            'version_number' => 1,
            'change_description' => fake()->sentence(),
        ];
    }

    /**
     * Set a specific version number.
     */
    public function version(int $number): static
    {
        return $this->state(fn (array $attributes) => [
            'version_number' => $number,
        ]);
    }

    /**
     * Set change description.
     */
    public function withChangeDescription(string $description): static
    {
        return $this->state(fn (array $attributes) => [
            'change_description' => $description,
        ]);
    }
}
