<?php

namespace App\Domain\Chat\Telegram\Application;

use Carbon\Carbon;

/**
 * Normalizes an inbound Telegram message payload into OpenCompany prompt text.
 *
 * Owns: the pure, deterministic mapping from a raw Telegram `message` array
 * (text, caption, media kinds, checklists, polls, locations, venues, contacts,
 * stories, paid media, ownership/managed-bot service messages, and modern
 * message-level metadata such as effects and suggested posts) to the
 * human-readable string stored as the OpenCompany message body and shown to
 * agents. Also owns the chat display-title rule shared by conversation mapping
 * and story labels.
 *
 * Does NOT own: persistence, workspace scoping, actor resolution, media
 * download, or dispatch. It performs no I/O, touches no models, and reads no
 * config, so it is safe to call from any webhook branch and to unit test
 * directly. The raw update stays durable in `telegram_update_receipts`; this
 * class only decides how much of it surfaces in the prompt text.
 *
 * Extracted verbatim from TelegramWebhookPipeline. Agent breadcrumb: the exact
 * output strings are part of the agent-facing prompt contract and are asserted
 * by ChatWebhookControllerTest, so treat wording changes as behavior changes.
 */
class TelegramMessageTextRenderer
{
    /**
     * Render the prompt text for an inbound (or edited) Telegram message.
     *
     * Text/caption wins when present, with modern metadata context appended
     * after a blank line. Otherwise the first matching structured payload kind
     * (in the fixed priority order below) produces the body. The match order
     * must not be reordered: a message can carry several keys (for example
     * `live_photo` and `photo`) and the first arm decides the visible label.
     *
     * @param  array<string, mixed>  $message
     */
    public function messageText(array $message): string
    {
        $text = (string) ($message['text'] ?? $message['caption'] ?? '');
        $context = $this->telegramMessageContextLines($message);
        if ($text !== '') {
            return trim($text.($context === [] ? '' : "\n\n".implode("\n", $context)));
        }

        $base = match (true) {
            isset($message['checklist']) && is_array($message['checklist']) => $this->checklistText($message['checklist']),
            isset($message['paid_media']) && is_array($message['paid_media']) => $this->paidMediaText($message['paid_media']),
            isset($message['live_photo']) && is_array($message['live_photo']) => $this->livePhotoText($message['live_photo']),
            isset($message['managed_bot_created']) && is_array($message['managed_bot_created']) => $this->managedBotCreatedText($message['managed_bot_created']),
            isset($message['poll_option_added']) && is_array($message['poll_option_added']) => $this->pollOptionAddedText($message['poll_option_added']),
            isset($message['poll_option_deleted']) && is_array($message['poll_option_deleted']) => $this->pollOptionDeletedText($message['poll_option_deleted']),
            isset($message['chat_owner_left']) && is_array($message['chat_owner_left']) => $this->chatOwnerLeftText($message['chat_owner_left']),
            isset($message['chat_owner_changed']) && is_array($message['chat_owner_changed']) => $this->chatOwnerChangedText($message['chat_owner_changed']),
            isset($message['photo']) => '[Telegram photo]',
            isset($message['document']) => '[Telegram document]',
            isset($message['voice']) => '[Telegram voice message]',
            isset($message['audio']) => '[Telegram audio]',
            isset($message['video']) => '[Telegram video]',
            isset($message['video_note']) => '[Telegram video note]',
            isset($message['animation']) => '[Telegram animation]',
            isset($message['sticker']) => '[Telegram sticker]',
            isset($message['story']) && is_array($message['story']) => $this->storyText($message['story']),
            isset($message['location']) && is_array($message['location']) => $this->locationText($message['location']),
            isset($message['venue']) && is_array($message['venue']) => $this->venueText($message['venue']),
            isset($message['contact']) && is_array($message['contact']) => $this->contactText($message['contact']),
            isset($message['poll']) && is_array($message['poll']) => $this->pollText($message['poll']),
            default => '[Telegram message]',
        };

        return trim($base.($context === [] ? '' : "\n".implode("\n", $context)));
    }

    /**
     * Preserve modern Telegram message metadata in the OpenCompany prompt text.
     *
     * Telegram continues to add message-level affordances such as effects,
     * Stars-backed paid posts, suggested posts, and checklist reply context. The
     * raw update remains in telegram_update_receipts, but the normalized message
     * should still carry enough human-readable context for agents and operators.
     *
     * @param  array<string, mixed>  $message
     * @return list<string>
     */
    private function telegramMessageContextLines(array $message): array
    {
        $lines = [];

        if (isset($message['effect_id']) && is_scalar($message['effect_id'])) {
            $lines[] = 'Telegram effect: '.(string) $message['effect_id'];
        }

        if (($message['is_paid_post'] ?? false) === true) {
            $lines[] = 'Telegram paid post';
        }

        if (isset($message['paid_star_count']) && is_numeric($message['paid_star_count'])) {
            $lines[] = 'Telegram paid stars: '.(string) $message['paid_star_count'];
        }

        if (isset($message['suggested_post_info']) && is_array($message['suggested_post_info'])) {
            $lines[] = $this->suggestedPostText($message['suggested_post_info']);
        }

        if (isset($message['reply_to_checklist_task_id']) && is_scalar($message['reply_to_checklist_task_id'])) {
            $lines[] = 'Reply to checklist task: '.(string) $message['reply_to_checklist_task_id'];
        }

        if (isset($message['reply_to_story']) && is_array($message['reply_to_story'])) {
            $lines[] = 'Reply to '.$this->storyText($message['reply_to_story']);
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $checklist
     */
    private function checklistText(array $checklist): string
    {
        $lines = ['Telegram checklist'];
        if (isset($checklist['title']) && is_string($checklist['title'])) {
            $lines[] = 'Title: '.$checklist['title'];
        }

        $tasks = is_array($checklist['tasks'] ?? null) ? $checklist['tasks'] : [];
        foreach (array_slice($tasks, 0, 20) as $task) {
            if (! is_array($task)) {
                continue;
            }

            $text = trim((string) ($task['text'] ?? 'Task'));
            $state = ($task['is_checked'] ?? false) === true ? '[x]' : '[ ]';
            $lines[] = "{$state} {$text}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $paidMedia
     */
    private function paidMediaText(array $paidMedia): string
    {
        $lines = ['Telegram paid media'];
        if (isset($paidMedia['star_count']) && is_numeric($paidMedia['star_count'])) {
            $lines[] = 'Stars: '.(string) $paidMedia['star_count'];
        }

        $items = is_array($paidMedia['paid_media'] ?? null) ? $paidMedia['paid_media'] : [];
        if ($items !== []) {
            $types = collect($items)
                ->filter(fn ($item) => is_array($item) && isset($item['type']))
                ->map(fn (array $item): string => (string) $item['type'])
                ->values()
                ->all();

            $lines[] = 'Items: '.count($items).($types === [] ? '' : ' ('.implode(', ', $types).')');
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $livePhoto
     */
    private function livePhotoText(array $livePhoto): string
    {
        $lines = ['Telegram live photo'];
        foreach (['width' => 'Width', 'height' => 'Height', 'duration' => 'Duration'] as $key => $label) {
            if (isset($livePhoto[$key]) && is_scalar($livePhoto[$key])) {
                $lines[] = "{$label}: ".(string) $livePhoto[$key];
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $managedBotCreated
     */
    private function managedBotCreatedText(array $managedBotCreated): string
    {
        $lines = ['Telegram managed bot created'];
        $bot = is_array($managedBotCreated['bot'] ?? null) ? $managedBotCreated['bot'] : [];
        $botLabel = $this->telegramUserLabel($bot);
        if ($botLabel !== null) {
            $lines[] = 'Bot: '.$botLabel;
        }

        if (isset($managedBotCreated['manage_url']) && is_string($managedBotCreated['manage_url'])) {
            $lines[] = 'Manage URL: '.$managedBotCreated['manage_url'];
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $pollOptionAdded
     */
    private function pollOptionAddedText(array $pollOptionAdded): string
    {
        $lines = ['Telegram poll option added'];
        if (isset($pollOptionAdded['poll_id']) && is_scalar($pollOptionAdded['poll_id'])) {
            $lines[] = 'Poll ID: '.(string) $pollOptionAdded['poll_id'];
        }

        $option = is_array($pollOptionAdded['option'] ?? null) ? $pollOptionAdded['option'] : [];
        if (isset($option['text']) && is_string($option['text'])) {
            $lines[] = 'Option: '.$option['text'];
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $pollOptionDeleted
     */
    private function pollOptionDeletedText(array $pollOptionDeleted): string
    {
        $lines = ['Telegram poll option deleted'];
        if (isset($pollOptionDeleted['poll_id']) && is_scalar($pollOptionDeleted['poll_id'])) {
            $lines[] = 'Poll ID: '.(string) $pollOptionDeleted['poll_id'];
        }
        if (isset($pollOptionDeleted['option_id']) && is_scalar($pollOptionDeleted['option_id'])) {
            $lines[] = 'Option ID: '.(string) $pollOptionDeleted['option_id'];
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $chatOwnerLeft
     */
    private function chatOwnerLeftText(array $chatOwnerLeft): string
    {
        $lines = ['Telegram chat owner left'];
        $user = is_array($chatOwnerLeft['old_owner'] ?? null) ? $chatOwnerLeft['old_owner'] : [];
        $label = $this->telegramUserLabel($user);
        if ($label !== null) {
            $lines[] = 'Previous owner: '.$label;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $chatOwnerChanged
     */
    private function chatOwnerChangedText(array $chatOwnerChanged): string
    {
        $lines = ['Telegram chat owner changed'];
        $oldOwner = is_array($chatOwnerChanged['old_owner'] ?? null) ? $chatOwnerChanged['old_owner'] : [];
        $newOwner = is_array($chatOwnerChanged['new_owner'] ?? null) ? $chatOwnerChanged['new_owner'] : [];

        $oldLabel = $this->telegramUserLabel($oldOwner);
        if ($oldLabel !== null) {
            $lines[] = 'Previous owner: '.$oldLabel;
        }

        $newLabel = $this->telegramUserLabel($newOwner);
        if ($newLabel !== null) {
            $lines[] = 'New owner: '.$newLabel;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function telegramUserLabel(array $user): ?string
    {
        $name = trim(((string) ($user['first_name'] ?? '')).' '.((string) ($user['last_name'] ?? '')));
        $username = isset($user['username']) && is_string($user['username']) ? '@'.$user['username'] : null;
        $id = isset($user['id']) && is_scalar($user['id']) ? (string) $user['id'] : null;

        $label = collect([$name !== '' ? $name : null, $username, $id ? "({$id})" : null])
            ->filter()
            ->implode(' ');

        return $label !== '' ? $label : null;
    }

    /**
     * @param  array<string, mixed>  $story
     */
    private function storyText(array $story): string
    {
        $chat = is_array($story['chat'] ?? null) ? $story['chat'] : [];
        $chatLabel = $this->chatTitle($chat) ?: (isset($chat['id']) ? (string) $chat['id'] : 'unknown chat');
        $storyId = isset($story['id']) ? (string) $story['id'] : 'unknown';

        return "Telegram story {$storyId} from {$chatLabel}";
    }

    /**
     * @param  array<string, mixed>  $suggestedPost
     */
    private function suggestedPostText(array $suggestedPost): string
    {
        $lines = ['Telegram suggested post'];

        if (isset($suggestedPost['state']) && is_scalar($suggestedPost['state'])) {
            $lines[] = 'State: '.(string) $suggestedPost['state'];
        }

        if (isset($suggestedPost['price']) && is_array($suggestedPost['price'])) {
            $currency = (string) ($suggestedPost['price']['currency'] ?? 'XTR');
            $amount = isset($suggestedPost['price']['amount']) ? (string) $suggestedPost['price']['amount'] : null;
            if ($amount !== null) {
                $lines[] = "Price: {$amount} {$currency}";
            }
        }

        if (isset($suggestedPost['send_date']) && is_numeric($suggestedPost['send_date'])) {
            $lines[] = 'Send date: '.Carbon::createFromTimestamp((int) $suggestedPost['send_date'])->toIso8601String();
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $location
     */
    private function locationText(array $location): string
    {
        $latitude = $location['latitude'] ?? null;
        $longitude = $location['longitude'] ?? null;
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return '[Telegram location]';
        }

        $lat = (string) $latitude;
        $lon = (string) $longitude;

        return implode("\n", [
            'Telegram location',
            "Latitude: {$lat}",
            "Longitude: {$lon}",
            "Map: https://maps.google.com/?q={$lat},{$lon}",
        ]);
    }

    /**
     * @param  array<string, mixed>  $venue
     */
    private function venueText(array $venue): string
    {
        $lines = ['Telegram venue'];
        if (isset($venue['title']) && is_string($venue['title'])) {
            $lines[] = 'Title: '.$venue['title'];
        }
        if (isset($venue['address']) && is_string($venue['address'])) {
            $lines[] = 'Address: '.$venue['address'];
        }

        $location = is_array($venue['location'] ?? null) ? $venue['location'] : [];
        $locationText = $this->locationText($location);
        if ($locationText !== '[Telegram location]') {
            $lines[] = '';
            $lines[] = $locationText;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function contactText(array $contact): string
    {
        $firstName = trim((string) ($contact['first_name'] ?? ''));
        $lastName = trim((string) ($contact['last_name'] ?? ''));
        $name = trim($firstName.' '.$lastName);

        $lines = ['Telegram contact'];
        if ($name !== '') {
            $lines[] = "Name: {$name}";
        }
        if (isset($contact['phone_number']) && is_string($contact['phone_number'])) {
            $lines[] = 'Phone: '.$contact['phone_number'];
        }
        if (isset($contact['user_id'])) {
            $lines[] = 'Telegram user ID: '.(string) $contact['user_id'];
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $poll
     */
    private function pollText(array $poll): string
    {
        $lines = ['Telegram poll'];
        if (isset($poll['question']) && is_string($poll['question'])) {
            $lines[] = 'Question: '.$poll['question'];
        }

        $options = is_array($poll['options'] ?? null) ? $poll['options'] : [];
        foreach (array_slice($options, 0, 10) as $option) {
            if (! is_array($option)) {
                continue;
            }

            $label = (string) ($option['text'] ?? 'Option');
            $votes = isset($option['voter_count']) ? ' ('.((int) $option['voter_count']).' votes)' : '';
            $lines[] = '- '.$label.$votes;
        }

        return implode("\n", $lines);
    }

    /**
     * Resolve a display title for a Telegram chat object.
     *
     * Groups/channels use `title`; private chats fall back to the user's first
     * and last name. Returns null when neither exists so callers can choose
     * their own fallback (chat id, "unknown chat", or leaving the title unset).
     *
     * @param  array<string, mixed>  $chat
     */
    public function chatTitle(array $chat): ?string
    {
        $title = $chat['title'] ?? null;
        if (is_string($title) && $title !== '') {
            return $title;
        }

        $name = trim(((string) ($chat['first_name'] ?? '')).' '.((string) ($chat['last_name'] ?? '')));

        return $name !== '' ? $name : null;
    }
}
