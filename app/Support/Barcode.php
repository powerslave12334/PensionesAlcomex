<?php

namespace App\Support;

/**
 * Generador de código de barras CODE128 en SVG puro (sin dependencias).
 *
 * Se usa en el ticket térmico de 80 mm: el folio del contrato se codifica
 * para poder buscarlo y cobrarlo escaneándolo.
 */
class Barcode
{
    /** Anchuras (en módulos) de cada uno de los 107 símbolos CODE128. */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312',
        '132212', '221213', '221312', '231212', '112232', '122132', '122231', '113222',
        '123122', '123221', '223211', '221132', '221231', '213212', '223112', '312131',
        '311222', '321122', '321221', '312212', '322112', '322211', '212123', '212321',
        '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121',
        '313121', '211331', '231131', '213113', '213311', '213131', '311123', '311321',
        '331121', '312113', '312311', '332111', '314111', '221411', '431111', '111224',
        '111422', '121124', '121421', '141122', '141221', '112214', '112412', '122114',
        '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112',
        '421211', '212141', '214121', '412121', '111143', '111341', '131141', '114113',
        '114311', '411113', '411311', '113141', '114131', '311141', '411131', '211412',
        '211214', '211232', '2331112',
    ];

    /**
     * Devuelve el SVG del código de barras CODE128 del texto dado.
     *
     * @param  string  $data         Texto a codificar (se recomienda solo ASCII legible).
     * @param  float   $moduleMm     Ancho de cada módulo en milímetros.
     * @param  float   $heightMm     Alto de las barras en milímetros.
     * @param  int     $quietModules Zona blanca a cada lado (módulos).
     */
    public static function code128(
        string $data,
        float $moduleMm = 0.33,
        float $heightMm = 15.0,
        int $quietModules = 10
    ): string {
        $data = trim($data);

        if ($data === '') {
            return '';
        }

        // Juego C si son solo dígitos en cantidad par (más compacto); si no, B.
        $useC = ctype_digit($data) && strlen($data) % 2 === 0;

        $values = [];

        if ($useC) {
            $values[] = 105; // Start C
            for ($i = 0, $len = strlen($data); $i < $len; $i += 2) {
                $values[] = (int) substr($data, $i, 2);
            }
        } else {
            $values[] = 104; // Start B
            for ($i = 0, $len = strlen($data); $i < $len; $i++) {
                $code = ord($data[$i]);
                // Fuera del rango imprimible: se sustituye por espacio.
                $values[] = ($code >= 32 && $code <= 126) ? $code - 32 : 0;
            }
        }

        // Dígito verificador.
        $sum = $values[0];
        for ($i = 1, $n = count($values); $i < $n; $i++) {
            $sum += $values[$i] * $i;
        }
        $values[] = $sum % 103;
        $values[] = 106; // Stop

        // Barras reales (x en módulos).
        $bars    = [];
        $x       = $quietModules;

        foreach ($values as $value) {
            $pattern = self::PATTERNS[$value] ?? '';
            $isBar   = true;

            for ($k = 0, $len = strlen($pattern); $k < $len; $k++) {
                $width = (int) $pattern[$k];

                if ($isBar) {
                    $bars[] = ['x' => $x, 'w' => $width];
                }

                $x    += $width;
                $isBar = ! $isBar;
            }
        }

        $total   = $x + $quietModules;
        $widthMm = $total * $moduleMm;

        $rects = '';
        foreach ($bars as $bar) {
            $rects .= '<rect x="' . $bar['x'] . '" y="0" width="' . $bar['w'] . '" height="100"/>';
        }

        $label = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%.2fmm" height="%.2fmm" '
            . 'viewBox="0 0 %d 100" preserveAspectRatio="none" role="img" aria-label="%s">'
            . '<rect width="100%%" height="100%%" fill="#ffffff"/>'
            . '<g fill="#000000">%s</g></svg>',
            $widthMm,
            $heightMm,
            $total,
            $label,
            $rects
        );
    }
}
