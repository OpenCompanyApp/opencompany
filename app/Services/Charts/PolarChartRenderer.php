<?php

namespace App\Services\Charts;

class PolarChartRenderer extends ChartRenderer
{
    public function render(string $outputPath): void
    {
        $graph = new \PolarGraph($this->width, $this->height);
        $graph->SetScale('lin');
        $this->applyBasicStyle($graph);

        foreach ($this->config['series'] as $i => $series) {
            // PolarPlot expects flat [angle, radius, angle, radius, ...]
            $flatData = [];
            foreach ($series['values'] as $pair) {
                $flatData[] = $pair[0]; // angle
                $flatData[] = $pair[1]; // radius
            }

            $plot = new \PolarPlot($flatData);
            $color = $this->getSeriesColor($i);
            $plot->SetColor($color);

            $weight = $series['lineWeight'] ?? 2;
            $plot->SetWeight($this->scaledFont($weight));

            $fillColor = $series['fillColor'] ?? $color . '@0.3';
            $plot->SetFillColor($fillColor);

            if (!empty($series['name'])) {
                $plot->SetLegend($series['name']);
            }

            $graph->Add($plot);
        }

        if ($this->hasLegend()) {
            $graph->legend->SetFont(FF_DEFAULT, FS_NORMAL, $this->scaledFont(10));
            $graph->legend->SetFrameWeight(0);
            $graph->legend->SetPos(0.5, 0.97, 'center', 'bottom');
        }

        $graph->Stroke($outputPath);
    }
}
