<?php

namespace App\Services\Chat;

/**
 * Declares the chat-provider features OpenCompany may rely on at runtime.
 *
 * Chatogrator gives every adapter the same PHP interface, but provider support
 * is not uniform: some adapters intentionally no-op typing/reactions, some
 * throw NotImplementedError for history or mutation methods, and Telegram now
 * has an app-owned experience layer outside the generic adapter. This registry
 * is the app-side truth table that prevents UI, sync jobs, and future tools from
 * promising capabilities a provider cannot actually deliver.
 */
class ChatProviderCapabilities
{
    /**
     * @return array<string, array<string, bool>>
     */
    public function all(): array
    {
        return [
            'telegram' => $this->telegram(),
            'slack' => $this->slack(),
            'discord' => $this->discord(),
            'teams' => $this->teams(),
            'google_chat' => $this->googleChat(),
            'github_chat' => $this->commentProvider(),
            'linear_chat' => $this->commentProvider(),
        ];
    }

    /**
     * Return the canonical capability map for one provider.
     *
     * Unknown providers deliberately get a fully false map. Adding support for a
     * provider should be an explicit product decision, not an accidental effect
     * of a shared adapter interface.
     *
     * @return array<string, bool>
     */
    public function for(string $provider): array
    {
        return $this->all()[$this->normalize($provider)] ?? $this->base();
    }

    public function supports(string $provider, string $capability): bool
    {
        return $this->for($provider)[$capability] ?? false;
    }

    /**
     * @return array<string, bool>
     */
    private function base(): array
    {
        return [
            'app_owned_experience_layer' => false,
            'send_messages' => false,
            'edit_messages' => false,
            'delete_messages' => false,
            'pin_messages' => false,
            'reactions' => false,
            'files' => false,
            'typing' => false,
            'slash_commands' => false,
            'buttons' => false,
            'modals' => false,
            'ephemeral_messages' => false,
            'history_listing' => false,
            'channel_discovery' => false,
            'topics' => false,
            'private_topics' => false,
            'direct_message_topics' => false,
            'native_drafts' => false,
            'mini_app' => false,
            'webhook_proof' => false,
            'business_updates' => false,
            'guest_messages' => false,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function telegram(): array
    {
        return array_merge($this->base(), [
            'app_owned_experience_layer' => true,
            'send_messages' => true,
            'edit_messages' => true,
            'delete_messages' => true,
            'pin_messages' => true,
            'reactions' => true,
            'files' => true,
            'typing' => true,
            'slash_commands' => true,
            'buttons' => true,
            'topics' => true,
            'private_topics' => true,
            'direct_message_topics' => true,
            'native_drafts' => true,
            'mini_app' => true,
            'webhook_proof' => true,
            'business_updates' => true,
            'guest_messages' => true,
        ]);
    }

    /**
     * @return array<string, bool>
     */
    private function slack(): array
    {
        return array_merge($this->base(), [
            'send_messages' => true,
            'edit_messages' => true,
            'delete_messages' => true,
            'pin_messages' => true,
            'reactions' => true,
            'files' => true,
            'slash_commands' => true,
            'buttons' => true,
            'modals' => true,
            'ephemeral_messages' => true,
            'history_listing' => true,
            'channel_discovery' => true,
            'webhook_proof' => true,
        ]);
    }

    /**
     * @return array<string, bool>
     */
    private function discord(): array
    {
        return array_merge($this->base(), [
            'send_messages' => true,
            'edit_messages' => true,
            'delete_messages' => true,
            'pin_messages' => true,
            'reactions' => true,
            'typing' => true,
            'slash_commands' => true,
            'buttons' => true,
            'history_listing' => true,
            'channel_discovery' => true,
            'webhook_proof' => true,
        ]);
    }

    /**
     * @return array<string, bool>
     */
    private function teams(): array
    {
        return array_merge($this->base(), [
            'send_messages' => true,
            'edit_messages' => true,
            'delete_messages' => true,
            'typing' => true,
            'buttons' => true,
            'history_listing' => true,
            'webhook_proof' => true,
        ]);
    }

    /**
     * @return array<string, bool>
     */
    private function googleChat(): array
    {
        return array_merge($this->base(), [
            'send_messages' => true,
            'slash_commands' => true,
            'buttons' => true,
            'webhook_proof' => true,
        ]);
    }

    /**
     * GitHub and Linear can be represented as external conversation surfaces,
     * but their Chatogrator adapters currently do not perform live comment
     * posting/mutation. Keep them explicit so generic outbound sync skips them
     * instead of exercising placeholder adapter methods.
     *
     * @return array<string, bool>
     */
    private function commentProvider(): array
    {
        return array_merge($this->base(), [
            'webhook_proof' => true,
        ]);
    }

    private function normalize(string $provider): string
    {
        return match ($provider) {
            'github' => 'github_chat',
            'linear' => 'linear_chat',
            'google' => 'google_chat',
            'microsoft_teams' => 'teams',
            default => $provider,
        };
    }
}
