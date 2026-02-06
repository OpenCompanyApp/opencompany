<?php

namespace App\Services\Charts;

class PieChartRenderer extends ChartRenderer
{
    public function render(string $outputPath): void
    {
        $type = $this->config['type'];

        $graph = new \PieGraph($this->width, $this->height);

        $title = $this->config['title'] ?? '';
        if ($title) {
            $graph->title->Set($title);
            $graph->title->SetFont(FF_DEFAULT, FS_BOLD, $this->scaledFont(14));
            $graph->title->SetMargin($this->scaledFont(6));
        }

        $bgColor = $this->getOption('backgroundColor', 'white');
        $graph->SetMarginColor($bgColor);
        $graph->SetFrame(false);

        $series = $this->config['series'][0];
        $values = $series['values'];

        if ($type === 'pie_3d') {
            $plot = new \PiePlot3D($values);
            $angle = $this->getOption('threeDAngle', 50);
            $plot->SetAngle($angle);
        } elseif ($type === 'donut') {
            $plot = new \PiePlotC($values);
            $donutSize = $this->getOption('donutSize', 0.5);
            $plot->SetMidSize($donutSize);
        } else {
            $plot = new \PiePlot($values);
        }

        // Labels
        $labels = $this->config['labels'] ?? [];
        if (!empty($labels)) {
            $plot->SetLabels($labels);
        }

        // Colors
        $colors = [];
        $numSlices = count($values);
        for ($i = 0; $i < $numSlices; $i++) {
            $colors[] = $this->getSeriesColor($i);
        }
        $plot->SetSliceColors($colors);

        // Label type
        $showPercentage = $this->getOption('percentage', true);
        if ($showPercentage) {
            $plot->SetLabelType(PIE_VALUE_PER);
        } else {
            $plot->SetLabelType(PIE_VALUE_ABS);
        }

        $plot->SetLabelPos(1);
        $plot->value->SetFont(FF_DEFAULT, FS_NORMAL, $this->scaledFont(9));

        if ($type !== 'donut') {
            $plot->SetCenter(0.5, 0.55);
        }

        $graph->Add($plot);

        // Legend
        if (!empty($labels) && $this->getOption('legend', true)) {
            $graph->legend->SetFont(FF_DEFAULT, FS_NORMAL, $this->scaledFont(10));
            $graph->legend->SetFrameWeight(0);

            $legendPos = $this->getOption('legendPosition', 'bottom');
            match ($legendPos) {
                'top' => $graph->legend->SetPos(0.5, 0.06, 'center', 'top'),
                'right' => $graph->legend->SetPos(0.97, 0.5, 'right', 'center'),
                default => $graph->legend->SetPos(0.5, 0.95, 'center', 'bottom'),
            };
        }

        $graph->Stroke($outputPath);
    }
}
