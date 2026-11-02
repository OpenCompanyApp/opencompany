<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleContactsService;

class GoogleContactsCreate implements Tool
{
    public function __construct(
        private GoogleContactsService $service,
    ) {}

    public function name(): string
    {
        return 'google_contacts_create';
    }

    public function description(): string
    {
        return 'Create a new Google Contact with name, email, phone, company, title, address, and notes.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Contacts integration is not configured.');
            }

            $name = $args['name'] ?? '';
            if (empty($name)) {
                return ToolResult::error('name is required.');
            }

            $data = $this->buildContactData($args);

            $result = $this->service->createContact($data);
            $contact = GoogleContactsService::formatContact($result);

            return ToolResult::success(array_merge(
                ['message' => "Contact '{$name}' created."],
                $contact,
            ));
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContactData(array $args): array
    {
        $data = [];

        $name = $args['name'] ?? '';
        if ($name !== '') {
            $parts = explode(' ', $name, 2);
            $data['names'] = [[
                'givenName' => $parts[0],
                'familyName' => $parts[1] ?? '',
            ]];
        }

        $email = $args['email'] ?? '';
        if ($email !== '') {
            $data['emailAddresses'] = [['value' => $email]];
        }

        $phone = $args['phone'] ?? '';
        if ($phone !== '') {
            $data['phoneNumbers'] = [['value' => $phone]];
        }

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

        $address = $args['address'] ?? '';
        if ($address !== '') {
            $data['addresses'] = [['formattedValue' => $address]];
        }

        $notes = $args['notes'] ?? '';
        if ($notes !== '') {
            $data['biographies'] = [['value' => $notes, 'contentType' => 'TEXT_PLAIN']];
        }

        return $data;
    }

    public function parameters(): array
    {
        return [
            'name' => ['type' => 'string', 'required' => true, 'description' => 'Full name (e.g., "John Doe").'],
            'email' => ['type' => 'string', 'description' => 'Email address.'],
            'phone' => ['type' => 'string', 'description' => 'Phone number (e.g., "+1-555-0123").'],
            'company' => ['type' => 'string', 'description' => 'Company/organization name.'],
            'title' => ['type' => 'string', 'description' => 'Job title.'],
            'address' => ['type' => 'string', 'description' => 'Full address (e.g., "123 Main St, Springfield, IL 62701").'],
            'notes' => ['type' => 'string', 'description' => 'Notes or biography for the contact.'],
        ];
    }
}
