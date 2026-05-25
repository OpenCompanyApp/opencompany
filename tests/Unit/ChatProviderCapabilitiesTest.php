<?php

namespace Tests\Unit;

use App\Services\Chat\ChatProviderCapabilities;
use PHPUnit\Framework\TestCase;

/**
 * Protects the app-owned provider capability matrix used by external chat sync.
 */
class ChatProviderCapabilitiesTest extends TestCase
{
    public function test_every_configured_chat_provider_has_an_explicit_capability_map(): void
    {
        $capabilities = new ChatProviderCapabilities;

        foreach (array_keys(require __DIR__.'/../../config/chat_integrations.php') as $provider) {
            $map = $capabilities->for($provider);

            $this->assertArrayHasKey('send_messages', $map, "Missing send_messages for {$provider}");
            $this->assertArrayHasKey('history_listing', $map, "Missing history_listing for {$provider}");
            $this->assertArrayHasKey('app_owned_experience_layer', $map, "Missing ownership flag for {$provider}");
        }
    }

    public function test_telegram_is_app_owned_and_exposes_native_richness_without_fake_generic_features(): void
    {
        $capabilities = new ChatProviderCapabilities;

        $this->assertTrue($capabilities->supports('telegram', 'app_owned_experience_layer'));
        $this->assertTrue($capabilities->supports('telegram', 'topics'));
        $this->assertTrue($capabilities->supports('telegram', 'private_topics'));
        $this->assertTrue($capabilities->supports('telegram', 'direct_message_topics'));
        $this->assertTrue($capabilities->supports('telegram', 'native_drafts'));
        $this->assertTrue($capabilities->supports('telegram', 'mini_app'));
        $this->assertTrue($capabilities->supports('telegram', 'business_updates'));
        $this->assertTrue($capabilities->supports('telegram', 'guest_messages'));

        $this->assertFalse($capabilities->supports('telegram', 'modals'));
        $this->assertFalse($capabilities->supports('telegram', 'ephemeral_messages'));
        $this->assertFalse($capabilities->supports('telegram', 'history_listing'));
        $this->assertFalse($capabilities->supports('telegram', 'channel_discovery'));
    }

    public function test_comment_adapters_do_not_claim_unimplemented_live_chat_sync(): void
    {
        $capabilities = new ChatProviderCapabilities;

        foreach (['github_chat', 'github', 'linear_chat', 'linear'] as $provider) {
            $this->assertFalse($capabilities->supports($provider, 'send_messages'), "{$provider} should not claim generic send support.");
            $this->assertFalse($capabilities->supports($provider, 'edit_messages'), "{$provider} should not claim generic edit support.");
            $this->assertFalse($capabilities->supports($provider, 'typing'), "{$provider} should not claim typing support.");
            $this->assertTrue($capabilities->supports($provider, 'webhook_proof'), "{$provider} should still expose webhook proof support.");
        }
    }

    public function test_unsupported_provider_defaults_to_false(): void
    {
        $capabilities = new ChatProviderCapabilities;

        $this->assertFalse($capabilities->supports('unknown_chat', 'send_messages'));
        $this->assertFalse($capabilities->supports('unknown_chat', 'webhook_proof'));
    }
}
