<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDocsService;

class GoogleDocsInsertImage implements Tool
{
    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function name(): string
    {
        return 'google_docs_insert_image';
    }

    public function description(): string
    {
        return 'Insert an image from a URL into a Google Docs document. Supports PNG, JPEG, and GIF. Optionally specify width and height in points.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Docs integration is not configured.');
            }

            $documentId = $args['document_id'] ?? '';
            if (empty($documentId)) {
                return ToolResult::error('documentId is required.');
            }

            $imageUrl = $args['image_url'] ?? '';
            if (empty($imageUrl)) {
                return ToolResult::error('imageUrl is required.');
            }

            $index = $args['index'] ?? -1;
            $atEnd = $index === -1;

            $insertImage = [
                'uri' => (string) $imageUrl,
            ];

            if ($atEnd) {
                $insertImage['endOfSegmentLocation'] = ['segmentId' => ''];
            } else {
                $insertImage['location'] = ['index' => (int) $index];
            }

            // Optional size
            $width = $args['width'] ?? null;
            $height = $args['height'] ?? null;
            if ($width !== null || $height !== null) {
                $objectSize = [];
                if ($width !== null) {
                    $objectSize['width'] = ['magnitude' => (float) $width, 'unit' => 'PT'];
                }
                if ($height !== null) {
                    $objectSize['height'] = ['magnitude' => (float) $height, 'unit' => 'PT'];
                }
                $insertImage['objectSize'] = $objectSize;
            }

            $requests = [['insertInlineImage' => $insertImage]];

            $this->service->batchUpdate((string) $documentId, $requests);

            $location = $atEnd ? 'end of document' : "index {$index}";

            return ToolResult::success("Image inserted at {$location}.");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'document_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Docs document ID (from the URL).'],
            'image_url' => ['type' => 'string', 'required' => true, 'description' => 'Image URL (PNG/JPEG/GIF).'],
            'index' => ['type' => 'integer', 'description' => 'Insert position (1-based). Omit or -1 for end of document.'],
            'width' => ['type' => 'number', 'description' => 'Width in points (optional).'],
            'height' => ['type' => 'number', 'description' => 'Height in points (optional).'],
        ];
    }
}
