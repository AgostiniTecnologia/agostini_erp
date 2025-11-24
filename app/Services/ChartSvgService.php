<?php

namespace App\Services;

class ChartSvgService
{
    /* =====================================================
       GERA CHART DE BARRAS COMO SVG (mantido igual ao seu)
    ===================================================== */
    public function barChart(array $labels, array $values, int $width = 900, int $height = 300): string
    {
        if (empty($labels) || empty($values) || count($labels) !== count($values)) {
            return $this->svgMessage($width, $height, "Sem dados para gerar gráfico de barras");
        }

        $max = max($values);
        if ($max <= 0) {
            return $this->svgMessage($width, $height, "Valores inválidos para gráfico");
        }

        $count = count($values);
        $leftPad = 60;
        $rightPad = 20;
        $bottomPad = 50;
        $topPad = 30;

        $plotW = $width - $leftPad - $rightPad;
        $plotH = $height - $topPad - $bottomPad;

        $barGap = 10;
        $barWidth = intval(($plotW - ($barGap * ($count - 1))) / max(1, $count));
        if ($barWidth < 4) $barWidth = 4;

        $svg = "<?xml version='1.0' encoding='UTF-8'?>";
        $svg .= "<svg width='{$width}' height='{$height}' viewBox='0 0 {$width} {$height}' xmlns='http://www.w3.org/2000/svg'>";
        $svg .= "<style>
            text { font-family: Arial, Helvetica, sans-serif; fill: #333; }
            .title { font-size: 14px; font-weight: bold; }
            .axis { stroke: #e9e9e9; stroke-width: 1; }
            .label { font-size: 11px; }
            .value { font-size: 10px; }
        </style>";

        $svg .= "<rect width='100%' height='100%' fill='white'/>";

        // Grid
        $gridSteps = 5;
        for ($i = 0; $i <= $gridSteps; $i++) {
            $y = $topPad + ($plotH * $i / $gridSteps);
            $svg .= "<line x1='{$leftPad}' y1='{$y}' x2='" . ($width - $rightPad) . "' y2='{$y}' class='axis'/>";
        }

        // Barras
        $x = $leftPad;
        for ($i = 0; $i < $count; $i++) {
            $v = (float)$values[$i];
            $barH = intval(($v / $max) * $plotH);
            $y = $topPad + ($plotH - $barH);

            $svg .= "<rect x='{$x}' y='{$y}' width='{$barWidth}' height='{$barH}' fill='#3498db'/>";

            $label = htmlspecialchars($labels[$i], ENT_XML1 | ENT_QUOTES, 'UTF-8');

            $labelX = $x + ($barWidth / 2);
            $labelY = $topPad + $plotH + 18;

            $svg .= "<text x='{$labelX}' y='{$labelY}' transform='rotate(-45 {$labelX} {$labelY})' text-anchor='end' class='label'>{$label}</text>";
            $svg .= "<text x='{$labelX}' y='" . ($y - 5) . "' text-anchor='middle' class='value'>{$v}</text>";

            $x += $barWidth + $barGap;
        }

        $svg .= "<text x='10' y='18' class='title'>Ranking - Top itens (tempo médio)</text>";
        $svg .= "</svg>";
        return $svg;
    }

    /* =====================================================
       Mensagem fallback
    ===================================================== */
    private function svgMessage(int $width, int $height, string $msg): string
    {
        return "<?xml version='1.0' encoding='UTF-8'?>
        <svg width='{$width}' height='{$height}' xmlns='http://www.w3.org/2000/svg'>
            <rect width='100%' height='100%' fill='white'/>
            <text x='50%' y='50%' text-anchor='middle' dominant-baseline='middle'
                font-family='Arial' font-size='14'>{$msg}</text>
        </svg>";
    }
}
