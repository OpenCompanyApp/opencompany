<?php

namespace OpenCompany\Integrations\Google\Services;

use OpenCompany\Integrations\Google\GoogleClient;

class GoogleContactsService
{
    private const BASE_URL = 'https://people.googleapis.com/v1';

    private const DEFAULT_PERSON_FIELDS = 'names,emailAddresses,phoneNumbers,organizations,addresses';

    public function __construct(private GoogleClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Search contacts by name, email, or phone (fuzzy matching).
     *
     * @return array<string, mixed>
     */
    public function searchContacts(string $query, int $pageSize = 10, ?string $pageToken = null): array
    {
        $params = [
            'query' => $query,
            'readMask' => self::DEFAULT_PERSON_FIELDS,
            'pageSize' => (string) min($pageSize, 30),
        ];

        if ($pageToken !== null) {
            $params['pageToken'] = $pageToken;
        }

        return $this->client->get(self::BASE_URL . '/people:searchContacts', $params);
    }

    /**
     * Get a single contact's full details.
     *
     * @return array<string, mixed>
     */
    public function getContact(string $resourceName): array
    {
        return $this->client->get(self::BASE_URL . '/' . $resourceName, [
            'personFields' => self::DEFAULT_PERSON_FIELDS . ',biographies,websites,memberships',
        ]);
    }

    /**
     * List all contacts with pagination.
     *
     * @return array<string, mixed>
     */
    public function listContacts(int $pageSize = 20, ?string $pageToken = null): array
    {
        $params = [
            'personFields' => self::DEFAULT_PERSON_FIELDS,
            'pageSize' => (string) min($pageSize, 100),
            'sortOrder' => 'FIRST_NAME_ASCENDING',
        ];

        if ($pageToken !== null) {
            $params['pageToken'] = $pageToken;
        }

        return $this->client->get(self::BASE_URL . '/people/me/connections', $params);
    }

    /**
     * Create a new contact.
     *
     * @param  array<string, mixed>  $data  People API contact resource
     * @return array<string, mixed>
     */
    public function createContact(array $data): array
    {
        return $this->client->post(self::BASE_URL . '/people:createContact', $data);
    }

    /**
     * Update an existing contact.
     *
     * @param  array<string, mixed>  $data  Fields to update
     * @return array<string, mixed>
     */
    public function updateContact(string $resourceName, array $data, string $etag, string $updatePersonFields): array
    {
        $data['etag'] = $etag;

        $url = self::BASE_URL . '/' . $resourceName . ':updateContact';
        $url .= '?' . http_build_query(['updatePersonFields' => $updatePersonFields]);

        return $this->client->patch($url, $data);
    }

    /**
     * Delete a contact.
     */
    public function deleteContact(string $resourceName): void
    {
        $this->client->delete(self::BASE_URL . '/' . $resourceName . ':deleteContact');
    }

    /**
     * List all contact groups/labels.
     *
     * @return array<string, mixed>
     */
    public function listContactGroups(): array
    {
        return $this->client->get(self::BASE_URL . '/contactGroups', [
            'groupFields' => 'name,groupType,memberCount',
        ]);
    }

    /**
     * Flatten a People API person resource into a clean array.
     *
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    public static function formatContact(array $person): array
    {
        $contact = [
            'resourceName' => $person['resourceName'] ?? '',
        ];

        // Name — fall back to first email if no name exists
        $names = $person['names'] ?? [];
        if (! empty($names)) {
            $primary = self::findPrimary($names);
            $contact['name'] = $primary['displayName'] ?? '';
        } else {
            $emails = $person['emailAddresses'] ?? [];
            if (! empty($emails)) {
                $contact['name'] = $emails[0]['value'] ?? '';
            }
        }

        // Emails
        $emails = $person['emailAddresses'] ?? [];
        if (! empty($emails)) {
            $contact['emails'] = array_map(fn (array $e) => array_filter([
                'value' => $e['value'] ?? '',
                'type' => $e['type'] ?? '',
            ], fn ($v) => $v !== ''), $emails);
        }

        // Phones
        $phones = $person['phoneNumbers'] ?? [];
        if (! empty($phones)) {
            $contact['phones'] = array_map(fn (array $p) => array_filter([
                'value' => $p['value'] ?? '',
                'type' => $p['type'] ?? '',
            ], fn ($v) => $v !== ''), $phones);
        }

        // Organization
        $orgs = $person['organizations'] ?? [];
        if (! empty($orgs)) {
            $primary = self::findPrimary($orgs);
            if (isset($primary['name'])) {
                $contact['company'] = $primary['name'];
            }
            if (isset($primary['title'])) {
                $contact['title'] = $primary['title'];
            }
        }

        // Address
        $addresses = $person['addresses'] ?? [];
        if (! empty($addresses)) {
            $primary = self::findPrimary($addresses);
            $contact['address'] = $primary['formattedValue'] ?? '';
        }

        // Notes/bio
        $bios = $person['biographies'] ?? [];
        if (! empty($bios)) {
            $contact['notes'] = $bios[0]['value'] ?? '';
        }

        // Websites
        $websites = $person['websites'] ?? [];
        if (! empty($websites)) {
            $contact['websites'] = array_map(fn (array $w) => $w['value'] ?? '', $websites);
        }

        // Groups/memberships
        $memberships = $person['memberships'] ?? [];
        if (! empty($memberships)) {
            $groups = [];
            foreach ($memberships as $m) {
                $groupName = $m['contactGroupMembership']['contactGroupResourceName'] ?? '';
                if ($groupName !== '') {
                    $groups[] = $groupName;
                }
            }
            if (! empty($groups)) {
                $contact['groups'] = $groups;
            }
        }

        return array_filter($contact, fn ($v) => $v !== '' && $v !== []);
    }

    /**
     * Find the primary entry in a People API array field, or return the first.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private static function findPrimary(array $items): array
    {
        foreach ($items as $item) {
            $isPrimary = $item['metadata']['primary'] ?? $item['primary'] ?? false;
            if ($isPrimary) {
                return $item;
            }
        }

        return $items[0] ?? [];
    }
}
