<?php

namespace App\Services\Chat;

use App\Models\IntegrationSetting;
use OpenCompany\Chatogrator\Adapters\Discord\DiscordAdapter;
use OpenCompany\Chatogrator\Adapters\Slack\SlackAdapter;
use OpenCompany\Chatogrator\Adapters\Telegram\TelegramAdapter;
use OpenCompany\Chatogrator\Contracts\Adapter;

class ChatAdapterFactory
{
    public static function create(IntegrationSetting $setting): ?Adapter
    {
        return match ($setting->integration_id) {
            'telegram' => TelegramAdapter::fromConfig([
                'bot_token' => $setting->getConfigValue('api_key'),
                'webhook_secret' => $setting->getConfigValue('webhook_secret'),
                'bot_user_id' => $setting->getConfigValue('bot_user_id'),
                'bot_username' => $setting->getConfigValue('bot_username'),
            ]),
            'slack' => SlackAdapter::fromConfig([
                'bot_token' => $setting->getConfigValue('api_key'),
                'signing_secret' => $setting->getConfigValue('signing_secret'),
                'bot_user_id' => $setting->getConfigValue('bot_user_id'),
                'user_name' => $setting->getConfigValue('bot_name'),
            ]),
            'discord' => DiscordAdapter::fromConfig([
                'bot_token' => $setting->getConfigValue('api_key'),
                'public_key' => $setting->getConfigValue('public_key'),
                'application_id' => $setting->getConfigValue('application_id'),
            ]),
            default => null,
        };
    }
}
