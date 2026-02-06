<?php

namespace App\Services\Charts;

class ComboChartRenderer extends ChartRenderer
{
    public function render(string $outputPath): void
    {
        $graph = new \Graph($this->width, $this->height);
        $graph->SetScale('textlin');

        $topMargin = $this->hasLegend() ? 60 : 40;
        $margin = $this->getMargin(80, 30, $topMargin, 70);
        $graph->SetMargin($margin[0], $margin[1], $margin[2], $margin[3]);

        $this->applyCommonStyle($graph);

        $barPlots = [];
        $linePlots = [];

        foreach ($this->config['series'] as $i => $series) {
            // Default: first series is bar, rest are line
            $plotType = $series['plotType'] ?? ($i === 0 ? 'bar' : 'line');
            $color = $this->getSeriesColor($i);

            if ($plotType === 'bar') {
                $plot = new \BarPlot($series['values']);
                $plot->SetFillColor($color);
                $plot->SetColor($color . '@0.8');
                $plot->SetWidth(0.6);

                if ($this->getOption('showValues', false)) {
                    $plot->value->Show();
                    $plot->value->SetFont(FF_DEFAULT, FS_NORMAL, $this->scaledFont(8));
                    $format = $this->getOption('valueFormat', '%d');
                    $plot->value->SetFormat($format);
                }

                if (!empty($series['name'])) {
                    $plot->SetLegend($series['name']);
                }

                $barPlots[] = $plot;
            } else {
                $plot = new \LinePlot($series['values']);
                $plot->SetColor($color);

                $weight = $series['lineWeight'] ?? 2;
                $plot->SetWeight($this->scaledFont($weight));

                // Markers
                $marker = $series['marker'] ?? 'circle';
                $plot->mark->SetType($this->resolveMarker($marker, $i));
                $markerSize = $series['markerSize'] ?? 5;
                $plot->mark->SetSize($this->scaledFont($markerSize));
                $plot->mark->SetColor($color);
                $plot->mark->SetFillColor($color);

                if (!empty($series['name'])) {
                    $plot->SetLegend($series['name']);
                }

                $linePlots[] = $plot;
            }
        }

        // Add bars first (behind lines)
        if (count($barPlots) > 1) {
            $graph->Add(new \GroupBarPlot($barPlots));
        } elseif (count($barPlots) === 1) {
            $graph->Add($barPlots[0]);
        }

        // Add lines on top
        foreach ($linePlots as $plot) {
            $graph->Add($plot);
        }

        $this->applyLabels($graph);

        $graph->Stroke($outputPath);
    }
}
