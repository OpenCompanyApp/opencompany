<?php

namespace OpenCompany\Integrations\Google\Services;

use OpenCompany\Integrations\Google\GoogleClient;

class GoogleCalendarService
{
    private const BASE_URL = 'https://www.googleapis.com/calendar/v3';

    public function __construct(private GoogleClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * List calendars the user has access to.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function listCalendars(array $params = []): array
    {
        return $this->client->get(self::BASE_URL . '/users/me/calendarList', $params);
    }

    /**
     * List events in a calendar.
     *
     * @param  array<string, mixed>  $params  timeMin, timeMax, q, maxResults, pageToken, singleEvents, orderBy
     * @return array<string, mixed>
     */
    public function listEvents(string $calendarId, array $params = []): array
    {
        $calendarId = urlencode($calendarId);

        return $this->client->get(self::BASE_URL . "/calendars/{$calendarId}/events", $params);
    }

    /**
     * Get a single event by ID.
     *
     * @return array<string, mixed>
     */
    public function getEvent(string $calendarId, string $eventId): array
    {
        $calendarId = urlencode($calendarId);
        $eventId = urlencode($eventId);

        return $this->client->get(self::BASE_URL . "/calendars/{$calendarId}/events/{$eventId}");
    }

    /**
     * Create an event.
     *
     * @param  array<string, mixed>  $data  summary, description, start, end, location, attendees, etc.
     * @return array<string, mixed>
     */
    public function createEvent(string $calendarId, array $data): array
    {
        $calendarId = urlencode($calendarId);

        return $this->client->post(self::BASE_URL . "/calendars/{$calendarId}/events", $data);
    }

    /**
     * Update an event (partial update via PATCH).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateEvent(string $calendarId, string $eventId, array $data): array
    {
        $calendarId = urlencode($calendarId);
        $eventId = urlencode($eventId);

        return $this->client->patch(self::BASE_URL . "/calendars/{$calendarId}/events/{$eventId}", $data);
    }

    /**
     * Delete an event.
     */
    public function deleteEvent(string $calendarId, string $eventId): void
    {
        $calendarId = urlencode($calendarId);
        $eventId = urlencode($eventId);

        $this->client->delete(self::BASE_URL . "/calendars/{$calendarId}/events/{$eventId}");
    }

    /**
     * Quick-add an event using natural language text.
     *
     * @return array<string, mixed>
     */
    public function quickAddEvent(string $calendarId, string $text): array
    {
        $calendarId = urlencode($calendarId);

        return $this->client->post(
            self::BASE_URL . "/calendars/{$calendarId}/events/quickAdd",
            ['text' => $text]
        );
    }

    /**
     * Query free/busy information.
     *
     * @param  array<string, mixed>  $data  timeMin, timeMax, items (array of {id})
     * @return array<string, mixed>
     */
    public function queryFreeBusy(array $data): array
    {
        return $this->client->post(self::BASE_URL . '/freeBusy', $data);
    }
}
