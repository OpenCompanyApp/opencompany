<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Channel>
 */
class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'ch' . fake()->unique()->randomNumber(5),
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(['public', 'private', 'agent']),
            'description' => fake()->sentence(),
            'creator_id' => User::factory(),
            'is_ephemeral' => false,
        ];
    }

    /**
     * Create a public channel.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'public',
        ]);
    }

    /**
     * Create a private channel.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'private',
        ]);
    }

    /**
     * Create an agent channel.
     */
    public function agent(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'agent',
        ]);
    }

    /**
     * Create a DM channel.
     */
    public function dm(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'dm',
        ]);
    }

    /**
     * Create a temporary channel.
     */
    public function temporary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_ephemeral' => true,
        ]);
    }
}
