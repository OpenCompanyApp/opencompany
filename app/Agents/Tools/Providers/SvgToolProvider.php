<?php

namespace App\Agents\Tools\Providers;

use App\Agents\Tools\Charts\RenderSvg;
use App\Models\User;
use Laravel\Ai\Contracts\Tool;

/**
 * Registers the SVG rendering utility.
 *
 * This sits outside the files/docs groups because it is a conversion primitive:
 * agents provide SVG markup and receive a generated PNG artifact.
 */
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

    public function createTool(string $class, User $agent, array $context = []): Tool
    {
        // RenderSvg is stateless and does not need the acting agent directly.
        return new RenderSvg;
    }
}
