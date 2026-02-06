<?php

namespace App\Services\Charts;

class ErrorLineChartRenderer extends ChartRenderer
{
    public function render(string $outputPath): void
    {
        $graph = new \Graph($this->width, $this->height);
        $graph->SetScale('textlin');

        $topMargin = $this->hasLegend() ? 60 : 40;
        $margin = $this->getMargin(80, 30, $topMargin, 70);
        $graph->SetMargin($margin[0], $margin[1], $margin[2], $margin[3]);

        $this->applyCommonStyle($graph);

        foreach ($this->config['series'] as $i => $series) {
            // LineErrorPlot expects flat: [val, errDeltaMin, errDeltaMax, val, errDeltaMin, errDeltaMax, ...]
            $flatData = [];
            foreach ($series['values'] as $triple) {
                $flatData[] = $triple[0]; // value
                $flatData[] = $triple[1]; // error delta min
                $flatData[] = $triple[2]; // error delta max
            }

            $plot = new \LineErrorPlot($flatData);
            $color = $this->getSeriesColor($i);
            $plot->SetColor($color);

            $weight = $series['lineWeight'] ?? 2;
            $plot->SetWeight($this->scaledFont($weight));

            if (!empty($series['name'])) {
                $plot->SetLegend($series['name']);
            }

            $graph->Add($plot);
        }

        $this->applyLabels($graph);

        $graph->Stroke($outputPath);
    }
}
