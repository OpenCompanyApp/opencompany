<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceDisk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * API for workspace storage backends.
 *
 * Disk config can contain credentials, so responses must use WorkspaceDisk's
 * safe serialization. FileSystemService is responsible for actually building
 * disks from these settings when files are read or written.
 */
class WorkspaceDiskController extends Controller
{
    public function index(): JsonResponse
    {
        $disks = WorkspaceDisk::forWorkspace()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $disks->map(fn (WorkspaceDisk $d) => $d->toSafeArray()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $workspaceId = workspace()->id;

        $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('workspace_disks')->where('workspace_id', $workspaceId),
            ],
            'driver' => 'required|string|in:local,s3,sftp',
            'config' => 'nullable|array',
        ]);

        $this->validateDriverConfig($request->input('driver'), $request->input('config', []));

        // The first disk becomes default so file operations always have a
        // storage target immediately after workspace setup.
        $isFirst = ! WorkspaceDisk::forWorkspace()->exists();

        $disk = WorkspaceDisk::create([
            'id' => Str::uuid()->toString(),
            'workspace_id' => $workspaceId,
            'name' => $request->input('name'),
            'driver' => $request->input('driver'),
            'config' => $request->input('config'),
            'is_default' => $isFirst,
            'enabled' => true,
        ]);

        return response()->json($disk->toSafeArray(), 201);
    }

    public function show(string $id): JsonResponse
    {
        $disk = WorkspaceDisk::forWorkspace()->findOrFail($id);

        return response()->json($disk->toSafeArray());
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $disk = WorkspaceDisk::forWorkspace()->findOrFail($id);
        $workspaceId = workspace()->id;

        $request->validate([
            'name' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('workspace_disks')->where('workspace_id', $workspaceId)->ignore($disk->id),
            ],
            'config' => 'sometimes|nullable|array',
            'enabled' => 'sometimes|boolean',
        ]);

        if ($request->has('name')) {
            $disk->name = $request->input('name');
        }

        if ($request->has('config')) {
            $newConfig = $request->input('config', []);
            $existingConfig = $disk->config ?? [];

            // Merge config while preserving masked secrets from the settings UI.
            // A mask means "keep encrypted value", not "save literal asterisks".
            foreach ($newConfig as $key => $value) {
                if (is_string($value) && str_contains($value, '****')) {
                    continue; // Keep existing value
                }
                $existingConfig[$key] = $value;
            }

            $disk->config = $existingConfig;
        }

        if ($request->has('enabled')) {
            if (! $request->input('enabled') && $disk->is_default) {
                return response()->json(['error' => 'Cannot disable the default disk.'], 422);
            }
            $disk->enabled = $request->input('enabled');
        }

        $disk->save();

        return response()->json($disk->toSafeArray());
    }

    public function destroy(string $id): JsonResponse
    {
        $disk = WorkspaceDisk::forWorkspace()->findOrFail($id);

        if ($disk->is_default) {
            return response()->json(['error' => 'Cannot delete the default disk. Set another disk as default first.'], 422);
        }

        $fileCount = $disk->files()->count();
        if ($fileCount > 0) {
            // Disk deletion is blocked while metadata still references it; the
            // app does not attempt cross-disk file migration here.
            return response()->json([
                'error' => "Cannot delete disk '{$disk->name}' — it still has {$fileCount} files. Move or delete them first.",
            ], 422);
        }

        $disk->delete();

        return response()->json(['success' => true]);
    }

    public function test(string $id): JsonResponse
    {
        $disk = WorkspaceDisk::forWorkspace()->findOrFail($id);

        try {
            $filesystem = $disk->buildFilesystem();
            $filesystem->directories('/');

            return response()->json([
                'success' => true,
                'message' => 'Connection successful.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: '.$e->getMessage(),
            ], 422);
        }
    }

    public function setDefault(string $id): JsonResponse
    {
        $disk = WorkspaceDisk::forWorkspace()->findOrFail($id);
        $workspaceId = workspace()->id;

        if (! $disk->enabled) {
            return response()->json(['error' => 'Cannot set a disabled disk as default.'], 422);
        }

        // Keep exactly one default disk per workspace.
        WorkspaceDisk::where('workspace_id', $workspaceId)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $disk->update(['is_default' => true]);

        return response()->json($disk->toSafeArray());
    }

    private function validateDriverConfig(string $driver, array $config): void
    {
        if ($driver === 's3') {
            // Validate only the fields needed to construct the Laravel disk.
            // Secret masking on updates is handled separately.
            $required = ['key', 'secret', 'region', 'bucket'];
            $missing = array_diff($required, array_keys(array_filter($config)));
            if ($missing) {
                abort(422, 'S3 requires: '.implode(', ', $missing));
            }
        }

        if ($driver === 'sftp') {
            // Password/private-key specifics are optional because different SFTP
            // setups authenticate differently, but host and username are always
            // needed to build the disk.
            $required = ['host', 'username'];
            $missing = array_diff($required, array_keys(array_filter($config)));
            if ($missing) {
                abort(422, 'SFTP requires: '.implode(', ', $missing));
            }
        }
    }
}
