<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'msg' . fake()->unique()->randomNumber(5),
            'content' => fake()->paragraph(),
            'author_id' => User::factory(),
            'channel_id' => Channel::factory(),
            'reply_to_id' => null,
            'is_approval_request' => false,
            'approval_request_id' => null,
            'is_pinned' => false,
            'pinned_by_id' => null,
            'pinned_at' => null,
            'timestamp' => now(),
        ];
    }

    /**
     * Create a pinned message.
     */
    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pinned' => true,
            'pinned_at' => now(),
            'pinned_by_id' => User::factory(),
        ]);
    }

    /**
     * Create a reply message.
     */
    public function reply(Message $parentMessage): static
    {
        return $this->state(fn (array $attributes) => [
            'reply_to_id' => $parentMessage->id,
            'channel_id' => $parentMessage->channel_id,
        ]);
    }

    /**
     * Create an approval request message.
     */
    public function approvalRequest(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approval_request' => true,
        ]);
    }
}
