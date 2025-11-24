<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiReportService
{
    protected string $apiKey;
    protected string $model;
    protected int $maxTokens;
    protected int $httpTimeout;
    protected ChartSvgService $chartService;

    public function __construct(ChartSvgService $chartService)
    {
        $this->apiKey = env('OPENAI_API_KEY', '');
        $this->model = env('OPENAI_MODEL', 'gpt-4.1-mini');
        $this->maxTokens = (int) env('AI_MAX_TOKENS', 1500);
        $this->httpTimeout = (int) env('AI_HTTP_TIMEOUT', 30);
        $this->chartService = $chartService;
    }

    public function analyze(string $systemPrompt, string $userContent, array $options = []): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException("OPENAI_API_KEY não está definido no .env");
        }

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ],
            'max_tokens' => $this->maxTokens,
            'temperature' => $options['temperature'] ?? 0.2,
        ];

        try {
            $resp = Http::withToken($this->apiKey)
                ->timeout($this->httpTimeout)
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            if ($resp->failed()) {
                Log::error('OpenAI API failed: ' . $resp->body());
                throw new \RuntimeException("OpenAI API falhou: " . substr($resp->body(), 0, 1000));
            }

            $json = $resp->json();
            return trim($json['choices'][0]['message']['content'] ?? "Erro: resposta inválida");
        } catch (\Exception $e) {
            Log::error('AiReportService::analyze error: ' . $e->getMessage());
            throw $e;
        }
    }

    /* ======================================================
       GERA PNG BASE64 – GRÁFICO DE BARRAS
    ====================================================== */
    public function makeBarChartBase64(array $labels, array $values, int $width = 900, int $height = 320): string
    {
        $max = max(1, max($values));

        $img = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        $gray = imagecolorallocate($img, 230, 230, 230);
        $barColor = imagecolorallocate($img, 52, 152, 219);

        imagefill($img, 0, 0, $white);

        // Grid
        for ($i = 0; $i <= 5; $i++) {
            $y = 40 + (($height - 80) * $i / 5);
            imageline($img, 60, $y, $width - 20, $y, $gray);
        }

        // Desenho das barras
        $count = count($values);
        $barWidth = 35;
        $gap = 25;
        $x = 70;

        for ($i = 0; $i < $count; $i++) {
            $val = $values[$i];
            $h = intval(($height - 100) * ($val / $max));

            imagefilledrectangle(
                $img,
                $x,
                $height - 40 - $h,
                $x + $barWidth,
                $height - 40,
                $barColor
            );

            imagestring($img, 3, $x, $height - 45 - $h, (string)$val, $black);

            // label vertical
            $labelClean = mb_strimwidth($labels[$i], 0, 18, '...');
            $chars = preg_split('//u', $labelClean, -1, PREG_SPLIT_NO_EMPTY);

            $ly = $height - 35;
            foreach ($chars as $ch) {
                imagestring($img, 2, $x + 10, $ly, $ch, $black);
                $ly += 8;
            }

            $x += $barWidth + $gap;
        }

        imagestring($img, 5, 20, 10, "Ranking - Top produtos (tempo médio)", $black);

        ob_start();
        imagepng($img);
        $raw = ob_get_clean();
        imagedestroy($img);

        return base64_encode($raw);
    }

    /* ======================================================
       LINE CHART + REGRESSÃO
    ====================================================== */
    public function makeLineChartWithRegressionBase64(array $pointsY, int $width = 900, int $height = 320): string
    {
        $count = count($pointsY);
        $max = max(1, max($pointsY));

        $img = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($img, 255,255,255);
        $gray = imagecolorallocate($img, 230,230,230);
        $black = imagecolorallocate($img, 0,0,0);
        $blue = imagecolorallocate($img, 0,120,255);
        $red = imagecolorallocate($img, 230,0,0);

        imagefill($img, 0,0,$white);

        for ($i = 0; $i <= 5; $i++) {
            $y = 40 + (($height - 80) * $i / 5);
            imageline($img, 50, $y, $width - 20, $y, $gray);
        }

        if ($count < 2) {
            imagestring($img, 5, 10, 10, "Dados insuficientes", $black);
        } else {
            $padLeft = 50; $padRight = 20; $padTop = 20; $padBottom = 40;
            $plotW = $width - ($padLeft + $padRight);
            $plotH = $height - ($padTop + $padBottom);

            $coords = [];
            for ($i=0;$i<$count;$i++) {
                $x = $padLeft + $plotW * ($i / max(1,$count-1));
                $y = $padTop + $plotH * (1 - ($pointsY[$i] / $max));
                $coords[] = [$x,$y];
            }

            // linha principal
            for ($i=0;$i<$count-1;$i++) {
                imageline($img,
                    intval($coords[$i][0]), intval($coords[$i][1]),
                    intval($coords[$i+1][0]), intval($coords[$i+1][1]),
                    $blue
                );
            }

            foreach ($coords as $c) {
                imagefilledellipse($img, intval($c[0]), intval($c[1]), 6,6, $blue);
            }

            // regressão corrigida
            [$m,$b] = $this->linearRegressionSafe($pointsY);

            $x1 = $padLeft;
            $y1 = $padTop + $plotH * (1 - (($m * 0 + $b)/$max));

            $x2 = $padLeft + $plotW;
            $y2 = $padTop + $plotH * (1 - (($m * ($count-1) + $b)/$max));

            imageline($img, intval($x1),intval($y1), intval($x2),intval($y2), $red);

            imagestring($img, 3, $width-260, 10, sprintf("Regressão: y = %.2f x + %.2f", $m, $b), $red);
        }

        imagestring($img, 5, 10, 10, "Timeline + Regressão", $black);

        ob_start();
        imagepng($img);
        $raw = ob_get_clean();
        imagedestroy($img);

        return base64_encode($raw);
    }

    /* ======================================================
       REGRESSÃO SEGURA (método correto)
    ====================================================== */
    private function linearRegressionSafe(array $y): array
    {
        $n = count($y);
        if ($n < 2) return [0, 0];

        $xSum = 0;
        $ySum = array_sum($y);
        $xxSum = 0;
        $xySum = 0;

        for ($i=0; $i<$n; $i++) {
            $xSum += $i;
            $xxSum += $i*$i;
            $xySum += $i*$y[$i];
        }

        $den = ($n * $xxSum) - ($xSum * $xSum);
        if ($den == 0) return [0, $ySum / $n];

        $m = (($n * $xySum) - ($xSum * $ySum)) / $den;
        $b = ($ySum - ($m * $xSum)) / $n;

        return [$m,$b];
    }
}
