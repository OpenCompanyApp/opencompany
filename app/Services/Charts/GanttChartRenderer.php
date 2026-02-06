<?php

namespace App\Services\Charts;

class GanttChartRenderer extends ChartRenderer
{
    public function render(string $outputPath): void
    {
        // Calculate height dynamically based on item count (vlines don't consume rows)
        $items = $this->config['items'] ?? [];
        $rowCount = 0;
        foreach ($items as $item) {
            if (($item['type'] ?? '') !== 'vline') {
                $rowCount++;
            }
        }
        $rowHeight = $this->scaledFont(22);
        $headerHeight = $this->scaledFont(40);
        $titleHeight = !empty($this->config['title']) ? $this->scaledFont(30) : 0;
        $calculatedHeight = ($rowCount * $rowHeight) + $headerHeight + $titleHeight + $this->scaledFont(30);
        $height = max($this->height, $calculatedHeight);

        $graph = new \GanttGraph($this->width, $height);
        $this->applyBasicStyle($graph);

        $graph->SetBox(false);
        $graph->SetShadow(false);
        $graph->scale->actinfo->SetFont(FF_DEFAULT, FS_NORMAL, $this->scaledFont(10));

        $items = $this->config['items'] ?? [];
        $row = 0;

        foreach ($items as $item) {
            $itemType = $item['type'];

            if ($itemType === 'bar') {
                $bar = new \GanttBar(
                    $row,
                    $item['label'],
                    $item['start'],
                    $item['end'],
                    $item['caption'] ?? '',
                    $item['heightFactor'] ?? 0.6
                );

                $color = $item['color'] ?? $this->getSeriesColor($row);
                $bar->SetPattern(GANTT_SOLID, $color);
                $bar->SetFillColor($color);
                $bar->title->SetFont(FF_DEFAULT, FS_NORMAL, $this->scaledFont(9));

                if (isset($item['progress'])) {
                    $bar->progress->Set(max(0, min(1, $item['progress'] / 100)));
                    $bar->progress->SetPattern(GANTT_SOLID, $this->darkenColor($color));
                }

                if (!empty($item['caption'])) {
                    $bar->caption->SetFont(FF_DEFAULT, FS_NORMAL, $this->scaledFont(8));
                }

                $graph->Add($bar);
                $row++;
            } elseif ($itemType === 'milestone') {
                $ms = new \MileStone($row, $item['label'], $item['date']);
                $color = $item['color'] ?? '#EF4444';
                $ms->mark->SetColor($color);
                $ms->mark->SetFillColor($color);
                $ms->title->SetFont(FF_DEFAULT, FS_NORMAL, $this->scaledFont(9));

                $graph->Add($ms);
                $row++;
            } elseif ($itemType === 'vline') {
                $color = $item['color'] ?? '#6366F1';
                $label = $item['label'] ?? '';
                $vline = new \GanttVLine($item['date'], $label, $color);
                $vline->SetDayOffset(0.5);
                if ($label) {
                    $vline->title->SetFont(FF_DEFAULT, FS_ITALIC, $this->scaledFont(8));
                }

                $graph->Add($vline);
                // vlines don't consume a row
            }
        }

        $graph->Stroke($outputPath);
    }

    private function darkenColor(string $hex): string
    {
        $hex = ltrim($hex, '#');
        $r = max(0, hexdec(substr($hex, 0, 2)) - 40);
        $g = max(0, hexdec(substr($hex, 2, 2)) - 40);
        $b = max(0, hexdec(substr($hex, 4, 2)) - 40);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
