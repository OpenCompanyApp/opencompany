<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleContactsService;

class GoogleContactsUpdate implements Tool
{
    public function __construct(
        private GoogleContactsService $service,
    ) {}

    public function name(): string
    {
        return 'google_contacts_update';
    }

    public function description(): string
    {
        return 'Update an existing Google Contact. Unspecified fields are preserved. Email, phone, and address are added alongside existing values; name, company, title, and notes are replaced.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Contacts integration is not configured.');
            }

            $resourceName = $args['resource_name'] ?? '';
            if (empty($resourceName)) {
                return ToolResult::error('resourceName is required.');
            }

            // Fetch current contact to get etag
            $current = $this->service->getContact($resourceName);
            $etag = $current['etag'] ?? '';
            if (empty($etag)) {
                return ToolResult::error('Could not retrieve contact etag. Contact may not exist.');
            }

            $data = $this->mergeContactData($current, $args);
            $updateFields = $this->getUpdateFields($args);

            if (empty($updateFields)) {
                return ToolResult::error('Provide at least one field to update (name, email, phone, company, title, address, notes).');
            }

            $result = $this->service->updateContact(
                $resourceName,
                $data,
                $etag,
                implode(',', $updateFields),
            );

            $contact = GoogleContactsService::formatContact($result);

            return ToolResult::success(array_merge(
                ['message' => 'Contact updated.'],
                $contact,
            ));
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private function mergeContactData(array $current, array $args): array
    {
        $data = [];

        // Name -- replace entirely (single-valued)
        $name = $args['name'] ?? '';
        if ($name !== '') {
            $parts = explode(' ', $name, 2);
            $data['names'] = [[
                'givenName' => $parts[0],
                'familyName' => $parts[1] ?? '',
            ]];
        }

        // Email -- append to existing (avoid duplicates)
        $email = $args['email'] ?? '';
        if ($email !== '') {
            $existing = $current['emailAddresses'] ?? [];
            $alreadyExists = false;
            foreach ($existing as $e) {
                if (strcasecmp($e['value'] ?? '', $email) === 0) {
                    $alreadyExists = true;
                    break;
                }
            }
            if (! $alreadyExists) {
                $existing[] = ['value' => $email];
            }
            $data['emailAddresses'] = $existing;
        }

        // Phone -- append to existing (avoid duplicates)
        $phone = $args['phone'] ?? '';
        if ($phone !== '') {
            $existing = $current['phoneNumbers'] ?? [];
            $alreadyExists = false;
            foreach ($existing as $p) {
                if (($p['value'] ?? '') === $phone) {
                    $alreadyExists = true;
                    break;
                }
            }
            if (! $alreadyExists) {
                $existing[] = ['value' => $phone];
            }
            $data['phoneNumbers'] = $existing;
        }

        // Organization -- replace (single-valued effectively)
        $company = $args['company'] ?? '';
        $title = $args['title'] ?? '';
        if ($company !== '' || $title !== '') {
            $org = [];
            if ($company !== '') {
                $org['name'] = $company;
            }
            if ($title !== '') {
                $org['title'] = $title;
            }
            $data['organizations'] = [$org];
        }

        // Address -- append to existing (avoid duplicates)
        $address = $args['address'] ?? '';
        if ($address !== '') {
            $existing = $current['addresses'] ?? [];
            $alreadyExists = false;
            foreach ($existing as $a) {
                if (($a['formattedValue'] ?? '') === $address) {
                    $alreadyExists = true;
                    break;
                }
            }
            if (! $alreadyExists) {
                $existing[] = ['formattedValue' => $address];
            }
            $data['addresses'] = $existing;
        }

        // Notes -- replace (single-valued)
        $notes = $args['notes'] ?? '';
        if ($notes !== '') {
            $data['biographies'] = [['value' => $notes, 'contentType' => 'TEXT_PLAIN']];
        }

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private function getUpdateFields(array $args): array
    {
        $fields = [];

        if (isset($args['name']) && $args['name'] !== '') {
            $fields[] = 'names';
        }
        if (isset($args['email']) && $args['email'] !== '') {
            $fields[] = 'emailAddresses';
        }
        if (isset($args['phone']) && $args['phone'] !== '') {
            $fields[] = 'phoneNumbers';
        }
        if ((isset($args['company']) && $args['company'] !== '') || (isset($args['title']) && $args['title'] !== '')) {
            $fields[] = 'organizations';
        }
        if (isset($args['address']) && $args['address'] !== '') {
            $fields[] = 'addresses';
        }
        if (isset($args['notes']) && $args['notes'] !== '') {
            $fields[] = 'biographies';
        }

        return $fields;
    }

    public function parameters(): array
    {
        return [
            'resource_name' => ['type' => 'string', 'required' => true, 'description' => 'Contact resource name (e.g., "people/c1234567890").'],
            'name' => ['type' => 'string', 'description' => 'Full name (e.g., "John Doe"). Replaces existing name.'],
            'email' => ['type' => 'string', 'description' => 'Email address. Added alongside existing emails.'],
            'phone' => ['type' => 'string', 'description' => 'Phone number (e.g., "+1-555-0123"). Added alongside existing phones.'],
            'company' => ['type' => 'string', 'description' => 'Company/organization name.'],
            'title' => ['type' => 'string', 'description' => 'Job title.'],
            'address' => ['type' => 'string', 'description' => 'Full address (e.g., "123 Main St, Springfield, IL 62701").'],
            'notes' => ['type' => 'string', 'description' => 'Notes or biography for the contact.'],
        ];
    }
}
