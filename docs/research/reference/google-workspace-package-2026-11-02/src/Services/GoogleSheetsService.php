<?php

namespace OpenCompany\Integrations\Google\Services;

use OpenCompany\Integrations\Google\GoogleClient;

class GoogleSheetsService
{
    private const BASE_URL = 'https://sheets.googleapis.com/v4/spreadsheets';

    /** @var array<string, int> */
    private array $sheetIdCache = [];

    public function __construct(private GoogleClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Get spreadsheet metadata (title, sheets list).
     *
     * @return array<string, mixed>
     */
    public function getMetadata(string $spreadsheetId): array
    {
        return $this->client->get(self::BASE_URL . '/' . $spreadsheetId, [
            'fields' => 'spreadsheetId,properties.title,sheets.properties',
        ]);
    }

    /**
     * Read a single range of values.
     *
     * @return array<string, mixed>
     */
    public function readRange(string $spreadsheetId, string $range, string $valueRenderOption = 'FORMATTED_VALUE'): array
    {
        return $this->client->get(self::BASE_URL . '/' . $spreadsheetId . '/values/' . urlencode($range), [
            'valueRenderOption' => $valueRenderOption,
        ]);
    }

    /**
     * Read multiple ranges in one call.
     *
     * @param  array<int, string>  $ranges
     * @return array<string, mixed>
     */
    public function batchGetRanges(string $spreadsheetId, array $ranges, string $valueRenderOption = 'FORMATTED_VALUE'): array
    {
        $query = [
            'valueRenderOption' => $valueRenderOption,
        ];

        foreach ($ranges as $range) {
            $query['ranges[]'] = $range;
        }

        // Build URL with multiple ranges params manually
        $url = self::BASE_URL . '/' . $spreadsheetId . '/values:batchGet';
        $params = ['valueRenderOption' => $valueRenderOption];
        $rangeParams = implode('&', array_map(fn (string $r) => 'ranges=' . urlencode($r), $ranges));
        $url .= '?' . http_build_query($params) . '&' . $rangeParams;

        return $this->client->get($url);
    }

    /**
     * Write values to a range.
     *
     * @param  array<int, array<int, mixed>>  $values
     * @return array<string, mixed>
     */
    public function writeRange(string $spreadsheetId, string $range, array $values, string $valueInputOption = 'USER_ENTERED'): array
    {
        $url = self::BASE_URL . '/' . $spreadsheetId . '/values/' . urlencode($range);
        $url .= '?' . http_build_query(['valueInputOption' => $valueInputOption]);

        return $this->client->put($url, [
            'range' => $range,
            'majorDimension' => 'ROWS',
            'values' => $values,
        ]);
    }

    /**
     * Append rows after the last row of data in a range.
     *
     * @param  array<int, array<int, mixed>>  $values
     * @return array<string, mixed>
     */
    public function appendRows(string $spreadsheetId, string $range, array $values, string $valueInputOption = 'USER_ENTERED'): array
    {
        $url = self::BASE_URL . '/' . $spreadsheetId . '/values/' . urlencode($range) . ':append';
        $url .= '?' . http_build_query([
            'valueInputOption' => $valueInputOption,
            'insertDataOption' => 'INSERT_ROWS',
        ]);

        return $this->client->post($url, [
            'range' => $range,
            'majorDimension' => 'ROWS',
            'values' => $values,
        ]);
    }

    /**
     * Clear all values from a range.
     *
     * @return array<string, mixed>
     */
    public function clearRange(string $spreadsheetId, string $range): array
    {
        return $this->client->post(
            self::BASE_URL . '/' . $spreadsheetId . '/values/' . urlencode($range) . ':clear',
            []
        );
    }

    /**
     * Write to multiple ranges in one call.
     *
     * @param  array<int, array{range: string, values: array<int, array<int, mixed>>}>  $data
     * @return array<string, mixed>
     */
    public function batchUpdateValues(string $spreadsheetId, array $data, string $valueInputOption = 'USER_ENTERED'): array
    {
        return $this->client->post(
            self::BASE_URL . '/' . $spreadsheetId . '/values:batchUpdate',
            [
                'valueInputOption' => $valueInputOption,
                'data' => array_map(fn (array $item) => [
                    'range' => $item['range'],
                    'majorDimension' => 'ROWS',
                    'values' => $item['values'],
                ], $data),
            ]
        );
    }

    /**
     * Execute structural batch update requests (add/delete/rename sheets, insert/delete rows/cols, etc.).
     *
     * @param  array<int, array<string, mixed>>  $requests
     * @return array<string, mixed>
     */
    public function batchUpdate(string $spreadsheetId, array $requests): array
    {
        return $this->client->post(
            self::BASE_URL . '/' . $spreadsheetId . ':batchUpdate',
            ['requests' => $requests]
        );
    }

    /**
     * Create a new spreadsheet.
     *
     * @return array<string, mixed>
     */
    public function createSpreadsheet(string $title): array
    {
        return $this->client->post(self::BASE_URL, [
            'properties' => ['title' => $title],
        ]);
    }

    /**
     * Resolve a sheet name to its numeric sheet ID.
     */
    public function resolveSheetId(string $spreadsheetId, string $sheetName): int
    {
        $cacheKey = $spreadsheetId . '::' . $sheetName;

        if (isset($this->sheetIdCache[$cacheKey])) {
            return $this->sheetIdCache[$cacheKey];
        }

        $metadata = $this->getMetadata($spreadsheetId);
        $sheets = $metadata['sheets'] ?? [];

        foreach ($sheets as $sheet) {
            $props = $sheet['properties'] ?? [];
            $name = $props['title'] ?? '';
            $id = (int) ($props['sheetId'] ?? 0);

            // Cache all sheets from this spreadsheet
            $this->sheetIdCache[$spreadsheetId . '::' . $name] = $id;

            if ($name === $sheetName) {
                return $id;
            }
        }

        throw new \RuntimeException("Sheet '{$sheetName}' not found in spreadsheet.");
    }

    /**
     * Format sheet metadata into a clean array.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public static function formatMetadata(array $metadata): array
    {
        $result = [
            'spreadsheetId' => $metadata['spreadsheetId'] ?? '',
            'title' => $metadata['properties']['title'] ?? '',
        ];

        $sheets = [];
        foreach ($metadata['sheets'] ?? [] as $sheet) {
            $props = $sheet['properties'] ?? [];
            $sheets[] = [
                'sheetId' => (int) ($props['sheetId'] ?? 0),
                'title' => $props['title'] ?? '',
                'index' => (int) ($props['index'] ?? 0),
                'rowCount' => (int) ($props['gridProperties']['rowCount'] ?? 0),
                'columnCount' => (int) ($props['gridProperties']['columnCount'] ?? 0),
            ];
        }

        $result['sheets'] = $sheets;

        return $result;
    }
}
