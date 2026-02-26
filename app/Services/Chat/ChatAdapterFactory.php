<?php

namespace App\Services\Chat;

use App\Models\IntegrationSetting;
use OpenCompany\Chatogrator\Adapters\Discord\DiscordAdapter;
use OpenCompany\Chatogrator\Adapters\GitHub\GitHubAdapter;
use OpenCompany\Chatogrator\Adapters\GoogleChat\GoogleChatAdapter;
use OpenCompany\Chatogrator\Adapters\Linear\LinearAdapter;
use OpenCompany\Chatogrator\Adapters\Slack\SlackAdapter;
use OpenCompany\Chatogrator\Adapters\Teams\TeamsAdapter;
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
                'gateway_secret' => $setting->getConfigValue('gateway_secret'),
            ]),
            'teams' => TeamsAdapter::fromConfig([
                'app_id' => $setting->getConfigValue('app_id'),
                'app_password' => $setting->getConfigValue('app_password'),
                'tenant_id' => $setting->getConfigValue('tenant_id'),
                'bot_name' => $setting->getConfigValue('bot_name'),
            ]),
            'google_chat' => GoogleChatAdapter::fromConfig([
                'service_account_json' => $setting->getConfigValue('service_account_json'),
                'project_id' => $setting->getConfigValue('project_id'),
                'webhook_secret' => $setting->getConfigValue('webhook_secret'),
                'bot_name' => $setting->getConfigValue('bot_name'),
                'bot_user_id' => $setting->getConfigValue('bot_user_id'),
            ]),
            'github_chat' => GitHubAdapter::fromConfig([
                'token' => $setting->getConfigValue('token'),
                'webhook_secret' => $setting->getConfigValue('webhook_secret'),
                'bot_name' => $setting->getConfigValue('bot_name'),
                'bot_user_id' => $setting->getConfigValue('bot_user_id'),
            ]),
            'linear_chat' => LinearAdapter::fromConfig([
                'api_key' => $setting->getConfigValue('api_key'),
                'webhook_secret' => $setting->getConfigValue('webhook_secret'),
                'bot_name' => $setting->getConfigValue('bot_name'),
                'bot_user_id' => $setting->getConfigValue('bot_user_id'),
            ]),
            default => null,
        };
    }
}
