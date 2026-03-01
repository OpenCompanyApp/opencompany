<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class WorkspaceDisk extends Model
{
    use BelongsToWorkspace;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'name',
        'driver',
        'config',
        'is_default',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
            'is_default' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        $array['isDefault'] = $this->is_default;

        // Never expose raw config — use toSafeArray() for frontend
        unset($array['config']);

        return $array;
    }

    /** @return HasMany<WorkspaceFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(WorkspaceFile::class);
    }

    /**
     * Build a Laravel Filesystem instance from this disk's config.
     */
    public function buildFilesystem(): Filesystem
    {
        if (in_array($this->driver, ['local', 'public'])) {
            return Storage::disk($this->driver);
        }

        return Storage::build(array_merge(
            ['driver' => $this->driver],
            $this->config ?? [],
        ));
    }

    /**
     * Return a frontend-safe representation with masked secrets.
     */
    public function toSafeArray(): array
    {
        $config = $this->config ?? [];
        $masked = [];
        $secretKeys = ['secret', 'key', 'password', 'private_key', 'access_key', 'secret_key'];

        foreach ($config as $k => $v) {
            if (!$v) {
                continue;
            }
            $isSecret = false;
            foreach ($secretKeys as $secretKey) {
                if (str_contains(strtolower($k), $secretKey)) {
                    $isSecret = true;
                    break;
                }
            }
            $masked[$k] = $isSecret ? $this->maskValue($v) : $v;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'driver' => $this->driver,
            'isDefault' => $this->is_default,
            'enabled' => $this->enabled,
            'config' => $masked,
            'fileCount' => $this->files()->count(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    private function maskValue(string $value): string
    {
        if (strlen($value) <= 8) {
            return '********';
        }

        return substr($value, 0, 4) . '****' . substr($value, -4);
    }
}
