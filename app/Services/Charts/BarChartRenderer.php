<?php

namespace App\Services\Charts;

class BarChartRenderer extends ChartRenderer
{
    public function render(string $outputPath): void
    {
        $type = $this->config['type'];
        $isHorizontal = $type === 'horizontal_bar';

        $graph = new \Graph($this->width, $this->height);
        $graph->SetScale('textlin');

        $topMargin = $this->hasLegend() ? 60 : 40;
        $margin = $isHorizontal
            ? $this->getMargin(100, 30, $topMargin, 50)
            : $this->getMargin(80, 30, $topMargin, 70);

        if ($isHorizontal) {
            $graph->Set90AndMargin($margin[0], $margin[1], $margin[2], $margin[3]);
        } else {
            $graph->SetMargin($margin[0], $margin[1], $margin[2], $margin[3]);
        }

        $this->applyCommonStyle($graph);

        $barPlots = [];
        foreach ($this->config['series'] as $i => $series) {
            $plot = new \BarPlot($series['values']);
            $color = $this->getSeriesColor($i);
            $plot->SetFillColor($series['fillColor'] ?? $color);
            $plot->SetColor($color);

            if (!empty($series['name'])) {
                $plot->SetLegend($series['name']);
            }

            if ($this->getOption('showValues', false)) {
                $plot->value->Show();
                $format = $this->getOption('valueFormat', '%d');
                $plot->value->SetFormat($format);
                $plot->value->SetFont(FF_DEFAULT, FS_NORMAL, $this->scaledFont(8));
            }

            $barPlots[] = $plot;
        }

        if (count($barPlots) === 1) {
            $graph->Add($barPlots[0]);
        } elseif ($type === 'stacked_bar') {
            $graph->Add(new \AccBarPlot($barPlots));
        } else {
            $graph->Add(new \GroupBarPlot($barPlots));
        }

        $this->applyLabels($graph);

        $graph->Stroke($outputPath);
    }
}
