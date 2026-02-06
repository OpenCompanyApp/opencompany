<?php

namespace App\Services\Charts;

class ContourChartRenderer extends ChartRenderer
{
    public function render(string $outputPath): void
    {
        $graph = new \Graph($this->width, $this->height);
        $graph->SetScale('intint');

        $topMargin = 50;
        $margin = $this->getMargin(60, 80, $topMargin, 50);
        $graph->SetMargin($margin[0], $margin[1], $margin[2], $margin[3]);

        $this->applyCommonStyle($graph);

        $matrix = $this->config['matrix'];
        $isobar = (int) $this->getOption('isobar', 10);
        $interpolation = max(1, min(5, (int) $this->getOption('interpolation', 1)));

        $plot = new \ContourPlot($matrix, $isobar, $interpolation);

        if ($this->getOption('legend', true)) {
            $plot->ShowLegend();
        }

        $graph->Add($plot);

        $graph->Stroke($outputPath);
    }
}
