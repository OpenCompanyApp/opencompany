<?php

namespace App\Agents\Tools;

use App\Models\User;
use App\Services\Charts\ChartService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateJpGraphChart implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return <<<'DESC'
Generate a chart image (PNG) and return a markdown embed. You have full creative control over resolution, colors, margins, fonts, markers, and styling.

**Chart types:**
- bar: Vertical bar chart
- horizontal_bar: Horizontal bar chart
- grouped_bar: Side-by-side bars comparing multiple series
- stacked_bar: Bars stacked on top of each other
- line: Line chart (single or multi-series, supports markers)
- area: Line chart with filled area beneath
- stacked_area: Multiple areas stacked on top of each other
- spline: Smooth curved line (auto-interpolated, min 3 data points)
- error_line: Line with error bars — values: [[value, errMin, errMax], ...]
- pie: Pie chart (first series only, labels = slice names)
- pie_3d: 3D perspective pie chart
- donut: Ring/donut chart with configurable hole size
- scatter: Scatter plot with [x, y] coordinate pairs
- impulse: Stem/lollipop chart — same [x, y] format as scatter
- radar: Spider/radar chart for comparing categories
- polar: Polar coordinate chart — values: [[angle, radius], ...]
- stock: Candlestick/OHLC financial chart — values: [[open,close,min,max], ...]
- box: Box plot (statistical) — values: [[low,q1,median,q3,high], ...]
- gantt: Gantt timeline — uses "items" (not series): [{type:"bar", label, start, end, progress?, color?}, {type:"milestone", label, date}, {type:"vline", date, label?, color?}]
- contour: Heatmap/contour — uses "matrix" (not series): 2D number grid [[z00,z01,...],[z10,...]]
- combo: Mixed bar+line on same chart — series with plotType:"bar"|"line"

**Series format:** [{name, values, color?, lineWeight?, fillColor?, marker?, markerSize?, plotType?}]
- marker options: "circle", "square", "diamond", "triangle", "triangle_down", "star", "cross", "x"
- plotType (combo only): "bar" or "line" (default: first=bar, rest=line)

**Options object (all optional):**
xAxisLabel, yAxisLabel, legend (bool), legendPosition ("top"/"bottom"/"right"), showValues (bool), valueFormat ("%d", "%.1f"), backgroundColor ("#hex"), gridColor ("#hex"), showGrid (bool), margin ({left, right, top, bottom} in px), labelAngle (degrees), yMin, yMax, percentage (bool, pie), threeDAngle (int, pie_3d), donutSize (0-1, donut), radarFill (bool), splineDensity (int, default 50), isobar (int, contour), interpolation (1-5, contour), colors (["#hex", ...] palette override)
DESC;
    }

    public function handle(Request $request): string
    {
        try {
            $config = [
                'type' => $request['type'],
                'title' => $request['title'] ?? '',
            ];

            // Series-based types use series, gantt uses items, contour uses matrix
            if (isset($request['series'])) {
                $config['series'] = $request['series'];
            }
            if (isset($request['items'])) {
                $config['items'] = $request['items'];
            }
            if (isset($request['matrix'])) {
                $config['matrix'] = $request['matrix'];
            }
            if (isset($request['labels'])) {
                $config['labels'] = $request['labels'];
            }
            if (isset($request['width'])) {
                $config['width'] = (int) $request['width'];
            }
            if (isset($request['height'])) {
                $config['height'] = (int) $request['height'];
            }
            if (isset($request['options'])) {
                $config['options'] = $request['options'];
            }

            $chartService = app(ChartService::class);
            $url = $chartService->generate($config);

            $title = $config['title'] ?: 'Chart';

            return "![{$title}]({$url})";
        } catch (\InvalidArgumentException $e) {
            return "Chart configuration error: {$e->getMessage()}";
        } catch (\Throwable $e) {
            return "Error generating chart: {$e->getMessage()}";
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema
                ->string()
                ->description("Chart type: 'bar', 'horizontal_bar', 'grouped_bar', 'stacked_bar', 'line', 'area', 'stacked_area', 'spline', 'error_line', 'pie', 'pie_3d', 'donut', 'scatter', 'impulse', 'radar', 'polar', 'stock', 'box', 'gantt', 'contour', 'combo'.")
                ->required(),
            'title' => $schema
                ->string()
                ->description('Chart title displayed at the top.'),
            'labels' => $schema
                ->array()
                ->description('Category labels: x-axis (bar/line/combo), slice names (pie), or axis names (radar). Array of strings.'),
            'series' => $schema
                ->array()
                ->description('Data series array (all types except gantt/contour). Each: {name: string, values: number[], color?: "#hex", lineWeight?: int, fillColor?: "#hex@alpha", marker?: "circle"|"square"|"diamond"|"triangle"|"star"|"cross", markerSize?: int, plotType?: "bar"|"line" (combo only)}. Scatter/impulse: values=[[x,y],...]. Polar: values=[[angle,radius],...]. Error_line: values=[[val,errMin,errMax],...]. Stock: values=[[open,close,min,max],...]. Box: values=[[low,q1,median,q3,high],...].'),
            'items' => $schema
                ->array()
                ->description('Gantt chart items (gantt type only). Each: {type: "bar"|"milestone"|"vline", label?: string, start?: "YYYY-MM-DD", end?: "YYYY-MM-DD", date?: "YYYY-MM-DD", progress?: 0-100, color?: "#hex", caption?: string}.'),
            'matrix' => $schema
                ->array()
                ->description('Contour/heatmap data (contour type only). 2D number grid: [[z00, z01, ...], [z10, z11, ...], ...]. Min 2x2.'),
            'width' => $schema
                ->integer()
                ->description('Chart width in pixels (default: 1400, max: 4000). Fonts scale with resolution.'),
            'height' => $schema
                ->integer()
                ->description('Chart height in pixels (default: 800, max: 4000).'),
            'options' => $schema
                ->object()
                ->description('Styling options: xAxisLabel, yAxisLabel, legend (bool), legendPosition ("top"/"bottom"/"right"), showValues (bool), valueFormat ("%d"), backgroundColor ("#hex"), gridColor ("#hex"), showGrid (bool), margin ({left,right,top,bottom}), labelAngle (int), yMin, yMax, percentage (bool), threeDAngle (int), donutSize (0-1), radarFill (bool), splineDensity (int), isobar (int, contour), interpolation (1-5, contour), colors (["#hex",...]).'),
        ];
    }
}
