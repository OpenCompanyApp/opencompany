<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDocsService;

class GoogleDocsAddBullets implements Tool
{
    /** @var array<int, string> Valid bullet presets */
    private const BULLET_PRESETS = [
        'BULLET_DISC_CIRCLE_SQUARE',
        'BULLET_DIAMONDX_ARROW3D_SQUARE',
        'BULLET_CHECKBOX',
        'BULLET_ARROW_DIAMOND_DISC',
        'BULLET_STAR_CIRCLE_SQUARE',
        'BULLET_ARROW3D_CIRCLE_SQUARE',
        'BULLET_LEFTTRIANGLE_DIAMOND_DISC',
        'BULLET_DIAMONDX_HOLLOWDIAMOND_SQUARE',
        'NUMBERED_DECIMAL_ALPHA_ROMAN',
        'NUMBERED_DECIMAL_ALPHA_ROMAN_PARENS',
        'NUMBERED_DECIMAL_NESTED',
        'NUMBERED_UPPERALPHA_ALPHA_ROMAN',
        'NUMBERED_UPPERROMAN_UPPERALPHA_DECIMAL',
        'NUMBERED_ZERODECIMAL_ALPHA_ROMAN',
    ];

    public function __construct(
        private GoogleDocsService $service,
    ) {}

    public function name(): string
    {
        return 'google_docs_add_bullets';
    }

    public function description(): string
    {
        return 'Add bullet or numbered list formatting to a range in a Google Docs document. Default preset is BULLET_DISC_CIRCLE_SQUARE. Use NUMBERED_DECIMAL_ALPHA_ROMAN for numbered lists.';
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

            $startIndex = $args['start_index'] ?? null;
            $endIndex = $args['end_index'] ?? null;
            if ($startIndex === null || $endIndex === null) {
                return ToolResult::error('startIndex and endIndex are required.');
            }

            $preset = (string) ($args['preset'] ?? 'BULLET_DISC_CIRCLE_SQUARE');
            if (! in_array($preset, self::BULLET_PRESETS, true)) {
                return ToolResult::error('Invalid preset. Valid values: ' . implode(', ', self::BULLET_PRESETS) . '.');
            }

            $requests = [
                ['createParagraphBullets' => [
                    'range' => [
                        'startIndex' => (int) $startIndex,
                        'endIndex' => (int) $endIndex,
                    ],
                    'bulletPreset' => $preset,
                ]],
            ];

            $this->service->batchUpdate((string) $documentId, $requests);

            return ToolResult::success("Bullet list ({$preset}) applied to range [{$startIndex}-{$endIndex}].");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'document_id' => ['type' => 'string', 'required' => true, 'description' => 'Google Docs document ID (from the URL).'],
            'start_index' => ['type' => 'integer', 'required' => true, 'description' => 'Start index of the range.'],
            'end_index' => ['type' => 'integer', 'required' => true, 'description' => 'End index of the range.'],
            'preset' => ['type' => 'string', 'description' => 'Bullet preset. Default BULLET_DISC_CIRCLE_SQUARE. Use NUMBERED_DECIMAL_ALPHA_ROMAN for numbered lists.'],
        ];
    }
}
