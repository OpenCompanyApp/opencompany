<?php

namespace App\Services\Charts;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use mitoteam\jpgraph\MtJpGraph;

class ChartService
{
    private const DEFAULT_WIDTH = 1400;
    private const DEFAULT_HEIGHT = 800;
    private const MIN_DIMENSION = 100;
    private const MAX_DIMENSION = 4000;

    private const VALID_TYPES = [
        'bar', 'horizontal_bar', 'grouped_bar', 'stacked_bar',
        'line', 'area', 'stacked_area', 'spline', 'error_line',
        'pie', 'pie_3d', 'donut',
        'scatter', 'impulse',
        'radar', 'polar',
        'stock', 'box',
        'gantt', 'contour', 'combo',
    ];

    private const MODULE_MAP = [
        'bar' => ['bar'],
        'horizontal_bar' => ['bar'],
        'grouped_bar' => ['bar'],
        'stacked_bar' => ['bar'],
        'line' => ['line'],
        'area' => ['line'],
        'stacked_area' => ['line'],
        'spline' => ['regstat', 'line'],
        'error_line' => ['line', 'error'],
        'pie' => ['pie'],
        'pie_3d' => ['pie', 'pie3d'],
        'donut' => ['pie'],
        'scatter' => ['scatter'],
        'impulse' => ['scatter'],
        'radar' => ['radar'],
        'polar' => ['polar'],
        'stock' => ['stock'],
        'box' => ['stock'],
        'gantt' => ['gantt'],
        'contour' => ['contour'],
        'combo' => ['bar', 'line'],
    ];

    /**
     * Generate a chart image from configuration.
     *
     * @return string Public URL path to the generated PNG
     */
    public function generate(array $config): string
    {
        $this->validate($config);

        $type = $config['type'];
        $width = $this->clampDimension($config['width'] ?? self::DEFAULT_WIDTH);
        $height = $this->clampDimension($config['height'] ?? self::DEFAULT_HEIGHT);

        // Load required JpGraph modules
        $modules = self::MODULE_MAP[$type] ?? [];
        MtJpGraph::load($modules);

        $filename = Str::uuid() . '.png';
        $relativePath = 'charts/' . $filename;

        Storage::disk('public')->makeDirectory('charts');
        $fullPath = Storage::disk('public')->path($relativePath);

        $renderer = $this->resolveRenderer($config, $width, $height);
        $renderer->render($fullPath);

        return '/storage/' . $relativePath;
    }

    private function validate(array $config): void
    {
        $type = $config['type'] ?? null;
        if (!$type || !in_array($type, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(
                'Invalid chart type. Supported: ' . implode(', ', self::VALID_TYPES)
            );
        }

        // Gantt uses items instead of series
        if ($type === 'gantt') {
            $this->validateGantt($config);
            return;
        }

        // Contour uses matrix instead of series
        if ($type === 'contour') {
            $this->validateContour($config);
            return;
        }

        // All other types use series
        $series = $config['series'] ?? [];
        if (empty($series) || !is_array($series)) {
            throw new \InvalidArgumentException('At least one data series is required.');
        }

        foreach ($series as $i => $s) {
            if (empty($s['values']) || !is_array($s['values'])) {
                throw new \InvalidArgumentException("Series {$i} must have a non-empty 'values' array.");
            }
        }

        // Label/axis charts need labels matching data length
        $labelTypes = [
            'bar', 'horizontal_bar', 'grouped_bar', 'stacked_bar',
            'line', 'area', 'stacked_area', 'spline', 'error_line',
            'combo',
        ];
        if (in_array($type, $labelTypes)) {
            $labels = $config['labels'] ?? [];
            if (!empty($labels)) {
                $expectedCount = count($series[0]['values']);
                if (count($labels) !== $expectedCount) {
                    throw new \InvalidArgumentException(
                        "Labels count (" . count($labels) . ") must match values count ({$expectedCount})."
                    );
                }
            }
        }

        // Scatter and impulse need [x, y] pairs
        if (in_array($type, ['scatter', 'impulse'])) {
            foreach ($series as $i => $s) {
                foreach ($s['values'] as $j => $pair) {
                    if (!is_array($pair) || count($pair) < 2) {
                        throw new \InvalidArgumentException(
                            "Scatter series {$i}, point {$j}: each value must be an [x, y] pair."
                        );
                    }
                }
            }
        }

        // Polar needs [angle, radius] pairs
        if ($type === 'polar') {
            foreach ($series as $i => $s) {
                foreach ($s['values'] as $j => $pair) {
                    if (!is_array($pair) || count($pair) < 2) {
                        throw new \InvalidArgumentException(
                            "Polar series {$i}, point {$j}: each value must be an [angle, radius] pair."
                        );
                    }
                }
            }
        }

        // Error line needs [value, errMin, errMax] triples
        if ($type === 'error_line') {
            foreach ($series as $i => $s) {
                foreach ($s['values'] as $j => $triple) {
                    if (!is_array($triple) || count($triple) < 3) {
                        throw new \InvalidArgumentException(
                            "Error line series {$i}, point {$j}: each value must be [value, errMin, errMax]."
                        );
                    }
                }
            }
        }

        // Spline needs at least 3 data points
        if ($type === 'spline') {
            foreach ($series as $i => $s) {
                if (count($s['values']) < 3) {
                    throw new \InvalidArgumentException(
                        "Spline series {$i}: at least 3 data points are required."
                    );
                }
            }
        }

        // Combo: validate plotType values
        if ($type === 'combo') {
            foreach ($series as $i => $s) {
                $plotType = $s['plotType'] ?? null;
                if ($plotType !== null && !in_array($plotType, ['bar', 'line'], true)) {
                    throw new \InvalidArgumentException(
                        "Combo series {$i}: plotType must be 'bar' or 'line'."
                    );
                }
            }
        }

        // Stock needs [open, close, min, max] tuples
        if ($type === 'stock') {
            foreach ($series as $i => $s) {
                foreach ($s['values'] as $j => $tuple) {
                    if (!is_array($tuple) || count($tuple) < 4) {
                        throw new \InvalidArgumentException(
                            "Stock series {$i}, point {$j}: each value must be [open, close, min, max]."
                        );
                    }
                }
            }
        }

        // Box needs [low, q1, median, q3, high] tuples
        if ($type === 'box') {
            foreach ($series as $i => $s) {
                foreach ($s['values'] as $j => $tuple) {
                    if (!is_array($tuple) || count($tuple) < 5) {
                        throw new \InvalidArgumentException(
                            "Box series {$i}, point {$j}: each value must be [low, q1, median, q3, high]."
                        );
                    }
                }
            }
        }
    }

    private function validateGantt(array $config): void
    {
        $items = $config['items'] ?? [];
        if (empty($items) || !is_array($items)) {
            throw new \InvalidArgumentException('Gantt chart requires a non-empty items array.');
        }

        foreach ($items as $i => $item) {
            $itemType = $item['type'] ?? null;
            if (!in_array($itemType, ['bar', 'milestone', 'vline'], true)) {
                throw new \InvalidArgumentException(
                    "Gantt item {$i}: type must be 'bar', 'milestone', or 'vline'."
                );
            }

            if ($itemType === 'bar') {
                if (empty($item['label']) || empty($item['start']) || empty($item['end'])) {
                    throw new \InvalidArgumentException(
                        "Gantt bar item {$i}: requires 'label', 'start', and 'end'."
                    );
                }
                if (!strtotime($item['start']) || !strtotime($item['end'])) {
                    throw new \InvalidArgumentException(
                        "Gantt bar item {$i}: 'start' and 'end' must be valid date strings."
                    );
                }
            }

            if ($itemType === 'milestone') {
                if (empty($item['label']) || empty($item['date'])) {
                    throw new \InvalidArgumentException(
                        "Gantt milestone item {$i}: requires 'label' and 'date'."
                    );
                }
            }

            if ($itemType === 'vline') {
                if (empty($item['date'])) {
                    throw new \InvalidArgumentException(
                        "Gantt vline item {$i}: requires 'date'."
                    );
                }
            }
        }
    }

    private function validateContour(array $config): void
    {
        $matrix = $config['matrix'] ?? [];
        if (empty($matrix) || !is_array($matrix)) {
            throw new \InvalidArgumentException('Contour chart requires a non-empty matrix (2D array).');
        }

        if (count($matrix) < 2) {
            throw new \InvalidArgumentException('Contour matrix must be at least 2x2.');
        }

        $rowLength = null;
        foreach ($matrix as $i => $row) {
            if (!is_array($row) || empty($row)) {
                throw new \InvalidArgumentException("Contour matrix row {$i} must be a non-empty array.");
            }
            if ($rowLength === null) {
                $rowLength = count($row);
                if ($rowLength < 2) {
                    throw new \InvalidArgumentException('Contour matrix must be at least 2x2.');
                }
            } elseif (count($row) !== $rowLength) {
                throw new \InvalidArgumentException(
                    "Contour matrix row {$i} has " . count($row) . " columns, expected {$rowLength}."
                );
            }
        }
    }

    private function resolveRenderer(array $config, int $width, int $height): ChartRenderer
    {
        return match ($config['type']) {
            'bar', 'horizontal_bar', 'grouped_bar', 'stacked_bar'
                => new BarChartRenderer($config, $width, $height),
            'line', 'area', 'stacked_area', 'spline'
                => new LineChartRenderer($config, $width, $height),
            'pie', 'pie_3d', 'donut'
                => new PieChartRenderer($config, $width, $height),
            'scatter', 'impulse'
                => new ScatterChartRenderer($config, $width, $height),
            'radar'
                => new RadarChartRenderer($config, $width, $height),
            'polar'
                => new PolarChartRenderer($config, $width, $height),
            'stock'
                => new StockChartRenderer($config, $width, $height),
            'box'
                => new BoxChartRenderer($config, $width, $height),
            'gantt'
                => new GanttChartRenderer($config, $width, $height),
            'contour'
                => new ContourChartRenderer($config, $width, $height),
            'error_line'
                => new ErrorLineChartRenderer($config, $width, $height),
            'combo'
                => new ComboChartRenderer($config, $width, $height),
        };
    }

    private function clampDimension(int $value): int
    {
        return max(self::MIN_DIMENSION, min(self::MAX_DIMENSION, $value));
    }
}
