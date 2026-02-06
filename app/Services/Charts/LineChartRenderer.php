<?php

namespace App\Services\Charts;

class LineChartRenderer extends ChartRenderer
{
    public function render(string $outputPath): void
    {
        $type = $this->config['type'];
        $isArea = in_array($type, ['area', 'stacked_area']);
        $isStacked = $type === 'stacked_area';
        $isSpline = $type === 'spline';

        $graph = new \Graph($this->width, $this->height);
        $graph->SetScale($isSpline ? 'linlin' : 'textlin');

        $topMargin = $this->hasLegend() ? 60 : 40;
        $margin = $this->getMargin(80, 30, $topMargin, 70);
        $graph->SetMargin($margin[0], $margin[1], $margin[2], $margin[3]);

        $this->applyCommonStyle($graph);

        $linePlots = [];
        foreach ($this->config['series'] as $i => $series) {
            $values = $series['values'];

            if ($isSpline) {
                $xData = array_keys($values);
                $splineDensity = $this->getOption('splineDensity', 50);
                $splineDensity = max(10, min(500, (int) $splineDensity));
                $spline = new \Spline($xData, $values);
                [$xNew, $yNew] = $spline->Get($splineDensity);
                $plot = new \LinePlot($yNew, $xNew);
            } else {
                $plot = new \LinePlot($values);
            }

            $color = $this->getSeriesColor($i);
            $plot->SetColor($color);

            $weight = $series['lineWeight'] ?? $this->getOption('lineWeight', 2);
            $plot->SetWeight($this->scaledFont($weight));

            if ($isArea) {
                $fillColor = $series['fillColor'] ?? $color . '@0.5';
                $plot->SetFillColor($fillColor);
            }

            // Markers (skip for spline — too many interpolated points)
            if (!$isSpline) {
                $marker = $series['marker'] ?? null;
                if ($marker) {
                    $plot->mark->SetType($this->resolveMarker($marker, $i));
                    $markerSize = $series['markerSize'] ?? 6;
                    $plot->mark->SetSize($this->scaledFont($markerSize));
                    $plot->mark->SetColor($color);
                    $plot->mark->SetFillColor($color);
                }
            }

            if (!empty($series['name'])) {
                $plot->SetLegend($series['name']);
            }

            $linePlots[] = $plot;
        }

        if ($isStacked && count($linePlots) > 1) {
            $graph->Add(new \AccLinePlot($linePlots));
        } else {
            foreach ($linePlots as $plot) {
                $graph->Add($plot);
            }
        }

        if (!$isSpline) {
            $this->applyLabels($graph);
        }

        $graph->Stroke($outputPath);
    }
}
