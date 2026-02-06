<?php

namespace App\Services\Charts;

class BoxChartRenderer extends ChartRenderer
{
    public function render(string $outputPath): void
    {
        $graph = new \Graph($this->width, $this->height);
        $graph->SetScale('textlin');

        $topMargin = $this->hasLegend() ? 60 : 40;
        $margin = $this->getMargin(80, 30, $topMargin, 70);
        $graph->SetMargin($margin[0], $margin[1], $margin[2], $margin[3]);

        $this->applyCommonStyle($graph);

        $series = $this->config['series'][0];
        $values = $series['values'];

        // BoxPlot expects a flat array: [low1, q11, median1, q31, high1, low2, ...]
        $flatData = [];
        foreach ($values as $tuple) {
            $flatData[] = $tuple[0]; // low
            $flatData[] = $tuple[1]; // q1
            $flatData[] = $tuple[2]; // median
            $flatData[] = $tuple[3]; // q3
            $flatData[] = $tuple[4]; // high
        }

        $plot = new \BoxPlot($flatData);
        $color = $this->getSeriesColor(0);
        $plot->SetColor($color);

        $graph->Add($plot);

        $this->applyLabels($graph);

        $graph->Stroke($outputPath);
    }
}
