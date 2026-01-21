<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DirectMessage>
 */
class DirectMessageFactory extends Factory
{
    protected $model = DirectMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'dm' . fake()->unique()->randomNumber(5),
            'user1_id' => User::factory(),
            'user2_id' => User::factory(),
            'channel_id' => Channel::factory()->direct(),
            'last_message_at' => now(),
        ];
    }

    /**
     * Set specific users for the DM.
     */
    public function between(User $user1, User $user2): static
    {
        return $this->state(fn (array $attributes) => [
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
        ]);
    }

    /**
     * Create a recent conversation.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_message_at' => now(),
        ]);
    }

    /**
     * Create an old conversation.
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_message_at' => now()->subDays(30),
        ]);
    }
}
