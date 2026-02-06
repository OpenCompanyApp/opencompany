<?php

namespace App\Services\Charts;

class StockChartRenderer extends ChartRenderer
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

        // StockPlot expects a flat array: [open1, close1, min1, max1, open2, close2, ...]
        $flatData = [];
        foreach ($values as $tuple) {
            $flatData[] = $tuple[0]; // open
            $flatData[] = $tuple[1]; // close
            $flatData[] = $tuple[2]; // min
            $flatData[] = $tuple[3]; // max
        }

        $plot = new \StockPlot($flatData);

        $graph->Add($plot);

        $this->applyLabels($graph);

        $graph->Stroke($outputPath);
    }
}
