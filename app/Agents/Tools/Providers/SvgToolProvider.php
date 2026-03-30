<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Charts\RenderSvg;
use App\Models\User;

class SvgToolProvider implements BuiltInToolProvider
{
    public function groupName(): string
    {
        return 'svg';
    }

    public function groupMeta(): array
    {
        return [
            'label' => 'render',
            'description' => 'Render SVG markup to PNG images',
        ];
    }

    public function groupIcon(): string
    {
        return 'ph:file-svg';
    }

    public function tools(): array
    {
        return [
            'render_svg' => [
                'class' => RenderSvg::class,
                'type' => 'write',
                'name' => 'Render SVG',
                'description' => 'Convert SVG markup to a PNG image.',
                'icon' => 'ph:file-svg',
            ],
        ];
    }

    public function createTool(string $class, User $agent, array $context = []): \Laravel\Ai\Contracts\Tool
    {
        return new RenderSvg;
    }
}
