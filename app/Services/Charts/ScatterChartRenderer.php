<?php

namespace App\Services\Charts;

class ScatterChartRenderer extends ChartRenderer
{
    public function render(string $outputPath): void
    {
        $isImpulse = $this->config['type'] === 'impulse';

        $graph = new \Graph($this->width, $this->height);
        $graph->SetScale('linlin');

        $topMargin = $this->hasLegend() ? 60 : 40;
        $margin = $this->getMargin(80, 30, $topMargin, 50);
        $graph->SetMargin($margin[0], $margin[1], $margin[2], $margin[3]);

        $this->applyCommonStyle($graph);

        foreach ($this->config['series'] as $i => $series) {
            $xData = array_column($series['values'], 0);
            $yData = array_column($series['values'], 1);

            $plot = new \ScatterPlot($yData, $xData);
            $color = $this->getSeriesColor($i);

            if ($isImpulse) {
                $plot->SetImpuls(true);
                $plot->SetColor($color);
                $plot->SetWeight($this->scaledFont($series['lineWeight'] ?? 2));
            }

            $marker = $series['marker'] ?? null;
            $markType = $marker
                ? $this->resolveMarker($marker, $i)
                : self::DEFAULT_MARKERS[$i % count(self::DEFAULT_MARKERS)];

            $plot->mark->SetType($markType);
            $plot->mark->SetColor($color);
            $plot->mark->SetFillColor($color);

            $markerSize = $series['markerSize'] ?? ($isImpulse ? 4 : 6);
            $plot->mark->SetSize($this->scaledFont($markerSize));

            if (!empty($series['name'])) {
                $plot->SetLegend($series['name']);
            }

            $graph->Add($plot);
        }

        $graph->Stroke($outputPath);
    }
}
