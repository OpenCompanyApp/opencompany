<?php

namespace App\Jobs;

use App\Ai\Agents\OneShotTextAgent;
use App\Agents\Providers\DynamicProviderResolver;
use App\Jobs\Concerns\SetsWorkspaceContext;
use App\Models\WorkspaceFile;
use App\Services\Ai\ModelCatalog;
use App\Services\Ai\ProviderConfigResolver;
use App\Services\FileSystemService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Files\LocalImage;
use Laravel\Ai\Transcription;

/**
 * Runs optional AI enrichment for Telegram-captured workspace files.
 *
 * Telegram webhooks must stay fast and durable, so the webhook pipeline only
 * captures bytes, records an enrichment contract in WorkspaceFile metadata, and
 * dispatches this worker. The worker owns the network/LLM side effects, writes
 * results back to file metadata, and treats provider failures as file-local
 * metadata failures rather than failed message ingestion.
 */
class EnrichTelegramMediaJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use SetsWorkspaceContext;

    public int $tries = 2;

    public int $timeout = 300;

    /** @var array<int, int> */
    public array $backoff = [30];

    public int $uniqueFor = 900;

    public function __construct(
        public WorkspaceFile $workspaceFile,
    ) {}

    public function uniqueId(): string
    {
        return 'telegram_media_enrichment:'.$this->workspaceFile->id;
    }

    public function handle(
        FileSystemService $files,
        DynamicProviderResolver $providers,
        ModelCatalog $models,
        ProviderConfigResolver $providerConfig
    ): void {
        $file = $this->workspaceFile->fresh();
        if (! $file instanceof WorkspaceFile) {
            return;
        }

        $this->setWorkspaceContext($file->workspace_id);

        $metadata = $file->metadata ?? [];
        $enrichment = is_array($metadata['telegram_enrichment'] ?? null)
            ? $metadata['telegram_enrichment']
            : null;

        if (! $this->isRunnableEnrichment($enrichment)) {
            return;
        }

        $cacheKey = (string) ($enrichment['cache_key'] ?? '');
        $cached = $cacheKey !== '' ? Cache::get($cacheKey) : null;
        if (is_array($cached)) {
            $this->storeEnrichment($file, array_merge($cached, [
                'status' => 'completed',
                'cached' => true,
                'completed_at' => now()->toISOString(),
            ]));

            return;
        }

        $this->storeEnrichment($file, [
            'status' => 'processing',
            'started_at' => now()->toISOString(),
        ]);

        $tempPath = null;

        try {
            $contents = $files->readFileContents($file);
            if (! is_string($contents) || $contents === '') {
                throw new \RuntimeException('Telegram media file has no readable bytes.');
            }

            $tempPath = $this->writeTempFile($file, $contents);
            $result = match ((string) $enrichment['type']) {
                'transcription' => $this->transcribe($file, $tempPath, $models, $providerConfig),
                'description' => $this->describeImage($file, $tempPath, $providers, $models),
                default => throw new \RuntimeException('Unsupported Telegram media enrichment type.'),
            };

            $result = array_merge($result, [
                'status' => 'completed',
                'completed_at' => now()->toISOString(),
            ]);

            if ($cacheKey !== '') {
                Cache::put($cacheKey, $result, now()->addDays(30));
            }

            $this->storeEnrichment($file, $result);
        } catch (\Throwable $e) {
            $this->storeEnrichment($file, [
                'status' => 'failed',
                'error_class' => $e::class,
                'error_message' => Str::limit($e->getMessage(), 500),
                'failed_at' => now()->toISOString(),
            ]);

            Log::warning('Telegram media enrichment failed', [
                'workspace_id' => $file->workspace_id,
                'workspace_file_id' => $file->id,
                'enrichment_type' => $enrichment['type'] ?? null,
                'error' => $e->getMessage(),
            ]);
        } finally {
            if (is_string($tempPath) && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $enrichment
     */
    private function isRunnableEnrichment(?array $enrichment): bool
    {
        return $enrichment !== null
            && ($enrichment['status'] ?? null) === 'queued'
            && in_array($enrichment['type'] ?? null, ['transcription', 'description'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function transcribe(
        WorkspaceFile $file,
        string $path,
        ModelCatalog $models,
        ProviderConfigResolver $providerConfig
    ): array
    {
        $provider = (string) config('telegram.media_transcription_provider', config('ai.default_for_transcription'));
        $configuredModel = config('telegram.media_transcription_model');

        // Register workspace-scoped credentials before invoking Laravel AI. If
        // no transcription-specific model is configured, let the provider use
        // its own default transcription model rather than a text-model default.
        $providerConfig->resolve(
            $provider,
            (string) ($configuredModel ?: $models->defaultModel($provider, $file->workspace_id)),
            $file->workspace_id,
        );

        $response = Transcription::fromPath($path, $file->mime_type)
            ->timeout((int) config('telegram.media_enrichment_timeout', 120))
            ->generate($provider, is_string($configuredModel) && $configuredModel !== '' ? $configuredModel : null);

        return [
            'type' => 'transcription',
            'text' => trim($response->text),
            'provider' => $response->meta->provider ?? null,
            'model' => $response->meta->model ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function describeImage(
        WorkspaceFile $file,
        string $path,
        DynamicProviderResolver $providers,
        ModelCatalog $models
    ): array {
        $provider = (string) config('telegram.media_description_provider', config('ai.default_for_images', config('ai.default')));
        $model = config('telegram.media_description_model')
            ?: $models->defaultModel($provider, $file->workspace_id);

        $providers->setWorkspaceId($file->workspace_id);
        $resolved = $providers->resolveFromParts($provider, (string) $model);

        $response = (new OneShotTextAgent(
            instructions: 'You describe Telegram image and sticker attachments for OpenCompany workspace search and follow-up.',
            maxTokens: 240,
            temperature: 0.2,
        ))->prompt(
            prompt: "Describe this Telegram attachment in 1-3 factual sentences. Mention visible text, objects, people, UI, or evidence. Do not infer private identities or hidden intent.",
            attachments: [new LocalImage($path, $file->mime_type)],
            provider: $resolved['provider'],
            model: $resolved['model'],
            timeout: (int) config('telegram.media_enrichment_timeout', 120),
        );

        return [
            'type' => 'description',
            'text' => trim($response->text),
            'provider' => $response->meta->provider ?? $resolved['provider'],
            'model' => $response->meta->model ?? $resolved['model'],
        ];
    }

    private function writeTempFile(WorkspaceFile $file, string $contents): string
    {
        $directory = storage_path('app/tmp/telegram-media-enrichment');
        File::ensureDirectoryExists($directory);

        $extension = pathinfo($file->name, PATHINFO_EXTENSION) ?: 'bin';
        $path = $directory.'/'.Str::uuid()->toString().'.'.$extension;
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function storeEnrichment(WorkspaceFile $file, array $changes): void
    {
        $metadata = $file->fresh()?->metadata ?? [];
        $current = is_array($metadata['telegram_enrichment'] ?? null)
            ? $metadata['telegram_enrichment']
            : [];

        $metadata['telegram_enrichment'] = array_merge($current, $changes);

        $file->forceFill(['metadata' => $metadata])->save();
    }
}
