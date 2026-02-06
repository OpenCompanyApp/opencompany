<?php

namespace App\Services\Charts;

use mitoteam\jpgraph\MtJpGraph;

abstract class ChartRenderer
{
    protected int $width;
    protected int $height;
    protected array $config;

    private const REFERENCE_WIDTH = 700;

    protected const DEFAULT_PALETTE = [
        '#4F46E5', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6',
        '#EC4899', '#06B6D4', '#84CC16', '#F97316', '#6366F1',
    ];

    protected const MARKER_MAP = [
        'circle' => MARK_FILLEDCIRCLE,
        'square' => MARK_SQUARE,
        'diamond' => MARK_DIAMOND,
        'triangle' => MARK_UTRIANGLE,
        'triangle_down' => MARK_DTRIANGLE,
        'star' => MARK_STAR,
        'cross' => MARK_CROSS,
        'x' => MARK_X,
    ];

    protected const DEFAULT_MARKERS = [
        MARK_FILLEDCIRCLE, MARK_SQUARE, MARK_DIAMOND,
        MARK_UTRIANGLE, MARK_DTRIANGLE, MARK_STAR,
    ];

    public function __construct(array $config, int $width, int $height)
    {
        $this->config = $config;
        $this->width = $width;
        $this->height = $height;
    }

    abstract public function render(string $outputPath): void;

    /**
     * Scale a base font size proportionally to image width.
     * At 700px reference width, returns the base size unchanged.
     * At 1400px, returns 2x the base size.
     */
    protected function scaledFont(int $baseSize): int
    {
        return (int) round($baseSize * ($this->width / self::REFERENCE_WIDTH));
    }

    protected function getSeriesColor(int $index): string
    {
        $seriesColor = $this->config['series'][$index]['color'] ?? null;
        if ($seriesColor) {
            return $seriesColor;
        }

        $palette = $this->config['options']['colors'] ?? null;
        if ($palette && isset($palette[$index])) {
            return $palette[$index];
        }

        return self::DEFAULT_PALETTE[$index % count(self::DEFAULT_PALETTE)];
    }

    protected function getOption(string $key, mixed $default = null): mixed
    {
        return $this->config['options'][$key] ?? $default;
    }

    /**
     * Get margin values, with user overrides applied.
     * Returns [left, right, top, bottom].
     */
    protected function getMargin(int $left, int $right, int $top, int $bottom): array
    {
        $custom = $this->getOption('margin');
        if (is_array($custom)) {
            $left = $custom['left'] ?? $left;
            $right = $custom['right'] ?? $right;
            $top = $custom['top'] ?? $top;
            $bottom = $custom['bottom'] ?? $bottom;
        }

        // Scale margins proportionally to resolution
        $scale = $this->width / self::REFERENCE_WIDTH;

        return [
            (int) round($left * $scale),
            (int) round($right * $scale),
            (int) round($top * $scale),
            (int) round($bottom * $scale),
        ];
    }

    protected function applyTitle(\Graph $graph): void
    {
        $title = $this->config['title'] ?? '';
        if ($title) {
            $graph->title->Set($title);
            $graph->title->SetFont(FF_DEFAULT, FS_BOLD, $this->scaledFont(14));
            $graph->title->SetMargin($this->scaledFont(6));
        }
    }

    protected function applyAxisLabels(\Graph $graph): void
    {
        $xLabel = $this->getOption('xAxisLabel');
        if ($xLabel) {
            $graph->xaxis->title->Set($xLabel);
            $graph->xaxis->title->SetFont(FF_DEFAULT, FS_NORMAL, $this->scaledFont(11));
            $graph->xaxis->title->SetMargin($this->scaledFont(6));
        }

        $yLabel = $this->getOption('yAxisLabel');
        if ($yLabel) {
            $graph->yaxis->title->Set($yLabel);
            $graph->yaxis->title->SetFont(FF_DEFAULT, FS_NORMAL, $this->scaledFont(11));
            $graph->yaxis->title->SetMargin($this->scaledFont(10));
        }
    }

    protected function applyLegend(\Graph $graph): void
    {
        $seriesCount = count($this->config['series'] ?? []);
        if ($seriesCount <= 1 || !$this->getOption('legend', true)) {
            return;
        }

        $graph->legend->SetFrameWeight(0);
        $graph->legend->SetFillColor('white@0.5');
        $graph->legend->SetFont(FF_DEFAULT, FS_NORMAL, $this->scaledFont(10));

        $position = $this->getOption('legendPosition', 'top');
        match ($position) {
            'bottom' => $graph->legend->SetPos(0.5, 0.97, 'center', 'bottom'),
            'right' => $graph->legend->SetPos(0.97, 0.5, 'right', 'center'),
            default => $graph->legend->SetPos(0.5, 0.06, 'center', 'top'),
        };

        $graph->legend->SetLayout(LEGEND_HOR);
        $graph->legend->SetColumns(min($seriesCount, 5));
    }

    protected function applyGrid(\Graph $graph): void
    {
        $showGrid = $this->getOption('showGrid', true);
        $gridColor = $this->getOption('gridColor', '#E5E7EB');

        $graph->ygrid->SetFill(false);
        $graph->ygrid->SetColor($gridColor);
        $graph->ygrid->Show($showGrid);
        $graph->xgrid->Show(false);
    }

    /**
     * Apply basic style to any graph type (including PieGraph, PolarGraph, GanttGraph).
     * Use this for specialized graph classes that don't have xaxis/yaxis.
     */
    protected function applyBasicStyle($graph): void
    {
        $title = $this->config['title'] ?? '';
        if ($title) {
            $graph->title->Set($title);
            $graph->title->SetFont(FF_DEFAULT, FS_BOLD, $this->scaledFont(14));
            $graph->title->SetMargin($this->scaledFont(6));
        }

        $bgColor = $this->getOption('backgroundColor', 'white');
        $graph->SetMarginColor($bgColor);
        $graph->SetFrame(false);
    }

    protected function applyCommonStyle(\Graph $graph): void
    {
        $bgColor = $this->getOption('backgroundColor', 'white');
        $graph->SetMarginColor($bgColor);
        $graph->SetFrame(false);

        $graph->xaxis->SetFont(FF_DEFAULT, FS_NORMAL, $this->scaledFont(10));
        $graph->yaxis->SetFont(FF_DEFAULT, FS_NORMAL, $this->scaledFont(10));

        // Apply y-axis range if specified
        $yMin = $this->getOption('yMin');
        $yMax = $this->getOption('yMax');
        if ($yMin !== null || $yMax !== null) {
            $graph->SetScale('textlin', $yMin, $yMax);
        }

        $this->applyTitle($graph);
        $this->applyGrid($graph);
        $this->applyLegend($graph);
        $this->applyAxisLabels($graph);
    }

    protected function applyLabels(\Graph $graph): void
    {
        $labels = $this->config['labels'] ?? [];
        if (empty($labels)) {
            return;
        }

        $graph->xaxis->SetTickLabels($labels);

        $labelAngle = $this->getOption('labelAngle');
        if ($labelAngle !== null) {
            $graph->xaxis->SetLabelAngle((int) $labelAngle);
        } elseif (count($labels) > 8) {
            $graph->xaxis->SetLabelAngle(45);
        }
    }

    protected function resolveMarker(string|int $name, int $fallbackIndex = 0): int
    {
        if (is_int($name)) {
            return $name;
        }

        return self::MARKER_MAP[$name] ?? self::DEFAULT_MARKERS[$fallbackIndex % count(self::DEFAULT_MARKERS)];
    }

    protected function hasLegend(): bool
    {
        return count($this->config['series'] ?? []) > 1 && $this->getOption('legend', true);
    }
}
