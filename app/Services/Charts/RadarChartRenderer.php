<?php

namespace App\Services\Charts;

class RadarChartRenderer extends ChartRenderer
{
    public function render(string $outputPath): void
    {
        $graph = new \RadarGraph($this->width, $this->height);

        $title = $this->config['title'] ?? '';
        if ($title) {
            $graph->title->Set($title);
            $graph->title->SetFont(FF_DEFAULT, FS_BOLD, $this->scaledFont(14));
            $graph->title->SetMargin($this->scaledFont(6));
        }

        $bgColor = $this->getOption('backgroundColor', 'white');
        $graph->SetMarginColor($bgColor);
        $graph->SetFrame(false);

        // Axis labels
        $labels = $this->config['labels'] ?? [];
        if (!empty($labels)) {
            $graph->SetTitles($labels);
        }
        $graph->axis->title->SetFont(FF_DEFAULT, FS_NORMAL, $this->scaledFont(10));

        $radarFill = $this->getOption('radarFill', true);

        foreach ($this->config['series'] as $i => $series) {
            $plot = new \RadarPlot($series['values']);
            $color = $this->getSeriesColor($i);
            $plot->SetColor($color);

            $weight = $series['lineWeight'] ?? 2;
            $plot->SetLineWeight($this->scaledFont($weight));

            if ($radarFill) {
                $fillColor = $series['fillColor'] ?? $color . '@0.5';
                $plot->SetFillColor($fillColor);
            }

            if (!empty($series['name'])) {
                $plot->SetLegend($series['name']);
            }

            $graph->Add($plot);
        }

        // Legend
        $seriesCount = count($this->config['series'] ?? []);
        if ($seriesCount > 1 && $this->getOption('legend', true)) {
            $graph->legend->SetFont(FF_DEFAULT, FS_NORMAL, $this->scaledFont(10));
            $graph->legend->SetFrameWeight(0);
            $graph->legend->SetFillColor('white@0.5');
            $graph->legend->SetPos(0.5, 0.06, 'center', 'top');
            $graph->legend->SetLayout(LEGEND_HOR);
        }

        $graph->Stroke($outputPath);
    }
}
