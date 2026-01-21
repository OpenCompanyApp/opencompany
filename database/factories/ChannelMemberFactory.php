<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChannelMember>
 */
class ChannelMemberFactory extends Factory
{
    protected $model = ChannelMember::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel_id' => Channel::factory(),
            'user_id' => User::factory(),
            'role' => 'member',
            'unread_count' => 0,
            'joined_at' => now(),
        ];
    }

    /**
     * Create an admin member.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    /**
     * Create a member with unread messages.
     */
    public function withUnread(int $count = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'unread_count' => $count,
        ]);
    }
}
