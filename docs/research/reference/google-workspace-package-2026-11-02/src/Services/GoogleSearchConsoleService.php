<?php

namespace OpenCompany\Integrations\Google\Services;

use OpenCompany\Integrations\Google\GoogleClient;

class GoogleSearchConsoleService
{
    private const BASE_URL = 'https://www.googleapis.com/webmasters/v3';

    private const INSPECTION_URL = 'https://searchconsole.googleapis.com/v1';

    public function __construct(private GoogleClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    // ─── Sites ───

    /**
     * List all verified sites/properties.
     *
     * @return array<string, mixed>
     */
    public function listSites(): array
    {
        return $this->client->get(self::BASE_URL . '/sites');
    }

    /**
     * Get a single site's details.
     *
     * @return array<string, mixed>
     */
    public function getSite(string $siteUrl): array
    {
        return $this->client->get(self::BASE_URL . '/sites/' . urlencode($siteUrl));
    }

    /**
     * Add/verify a site property.
     *
     * @return array<string, mixed>
     */
    public function addSite(string $siteUrl): array
    {
        return $this->client->put(self::BASE_URL . '/sites/' . urlencode($siteUrl));
    }

    /**
     * Delete a site property.
     */
    public function deleteSite(string $siteUrl): void
    {
        $this->client->delete(self::BASE_URL . '/sites/' . urlencode($siteUrl));
    }

    // ─── Search Analytics ───

    /**
     * Query search analytics (performance data).
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function queryAnalytics(string $siteUrl, array $body): array
    {
        return $this->client->post(
            self::BASE_URL . '/sites/' . urlencode($siteUrl) . '/searchAnalytics/query',
            $body
        );
    }

    // ─── URL Inspection ───

    /**
     * Inspect a URL's indexing status.
     *
     * @return array<string, mixed>
     */
    public function inspectUrl(string $siteUrl, string $inspectionUrl): array
    {
        return $this->client->post(self::INSPECTION_URL . '/urlInspection/index:inspect', [
            'inspectionUrl' => $inspectionUrl,
            'siteUrl' => $siteUrl,
        ]);
    }

    // ─── Sitemaps ───

    /**
     * List all sitemaps for a site.
     *
     * @return array<string, mixed>
     */
    public function listSitemaps(string $siteUrl): array
    {
        return $this->client->get(
            self::BASE_URL . '/sites/' . urlencode($siteUrl) . '/sitemaps'
        );
    }

    /**
     * Get a specific sitemap's details.
     *
     * @return array<string, mixed>
     */
    public function getSitemap(string $siteUrl, string $feedpath): array
    {
        return $this->client->get(
            self::BASE_URL . '/sites/' . urlencode($siteUrl) . '/sitemaps/' . urlencode($feedpath)
        );
    }

    /**
     * Submit a sitemap.
     *
     * @return array<string, mixed>
     */
    public function submitSitemap(string $siteUrl, string $feedpath): array
    {
        return $this->client->put(
            self::BASE_URL . '/sites/' . urlencode($siteUrl) . '/sitemaps/' . urlencode($feedpath)
        );
    }

    /**
     * Delete a sitemap.
     */
    public function deleteSitemap(string $siteUrl, string $feedpath): void
    {
        $this->client->delete(
            self::BASE_URL . '/sites/' . urlencode($siteUrl) . '/sitemaps/' . urlencode($feedpath)
        );
    }
}
