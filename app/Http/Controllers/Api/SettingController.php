<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\Memory\DocumentIndexingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SettingController extends Controller
{
    /**
     * Get all settings grouped by category, merged with defaults.
     */
    public function index()
    {
        return response()->json(AppSetting::allWithDefaults());
    }

    /**
     * Update settings for a specific category.
     */
    public function update(Request $request)
    {
        $request->validate([
            'category' => 'required|string|in:organization,agents,notifications,policies,memory',
            'settings' => 'required|array',
        ]);

        $category = $request->input('category');
        $settings = $request->input('settings');
        $defaults = AppSetting::defaults();

        // Only allow known keys for the category
        $allowedKeys = array_keys($defaults[$category] ?? []);
        $filtered = array_intersect_key($settings, array_flip($allowedKeys));

        // Detect if the embedding model is being changed
        $embeddingModelChanged = false;
        if ($category === 'memory' && isset($filtered['memory_embedding_model'])) {
            $currentModel = AppSetting::getValue('memory_embedding_model');
            if ($currentModel !== null && $currentModel !== $filtered['memory_embedding_model']) {
                $embeddingModelChanged = true;
            }
        }

        AppSetting::setMany($filtered, $category);

        // Reset embedding data when the model changes — old vectors are
        // incompatible with the new model's vector space.
        if ($embeddingModelChanged) {
            DocumentIndexingService::resetEmbeddings();
            Log::info('Embedding model changed, embedding data reset', [
                'new_model' => $filtered['memory_embedding_model'],
            ]);
        }

        return response()->json([
            'message' => $embeddingModelChanged
                ? 'Settings updated. Embedding data has been reset for the new model.'
                : 'Settings updated.',
            'settings' => AppSetting::getByCategory($category),
        ]);
    }

    /**
     * Handle danger zone actions.
     */
    public function dangerAction(Request $request)
    {
        $request->validate([
            'action' => 'required|string|in:pause_agents,reset_memory',
        ]);

        $action = $request->input('action');

        if ($action === 'pause_agents') {
            User::where('type', 'agent')
                ->whereNotIn('status', ['offline', 'paused'])
                ->update(['status' => 'paused']);

            return response()->json(['message' => 'All agents have been paused.']);
        }

        if ($action === 'reset_memory') {
            // Clear agent identity files (memory/context)
            User::where('type', 'agent')->each(function (User $agent) {
                $identityDir = storage_path("app/agents/{$agent->id}");
                $memoryFile = $identityDir . '/memory.md';
                if (file_exists($memoryFile)) {
                    file_put_contents($memoryFile, '');
                }
            });

            return response()->json(['message' => 'Agent memory has been reset.']);
        }

        return response()->json(['message' => 'Unknown action.'], 400);
    }
}
