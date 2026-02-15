<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => fn () => [
                'user' => $request->user(),
            ],
            'workspace' => function () {
                $workspace = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;

                return $workspace ? [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'slug' => $workspace->slug,
                    'icon' => $workspace->icon,
                    'color' => $workspace->color,
                    'owner_id' => $workspace->owner_id,
                ] : null;
            },
            'workspaceRole' => function () use ($request) {
                $workspace = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;
                $user = $request->user();

                return $workspace && $user
                    ? $user->currentWorkspaceRole($workspace)
                    : null;
            },
            'workspaces' => function () use ($request) {
                $user = $request->user();

                return $user
                    ? $user->workspaces()->select('workspaces.id', 'name', 'slug', 'icon', 'color')->get()
                    : [];
            },
        ];
    }
}
