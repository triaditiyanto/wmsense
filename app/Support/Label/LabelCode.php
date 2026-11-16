<?php

namespace App\Support\Label;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Picqer\Barcode\Types\TypeCode128;

/**
 * Gambar kode untuk label (SVG inline, tajam di printer apa pun).
 *
 *   LabelCode::qr('LPN-WH1-26-000001')       -> <svg> QR Code (error correction M)
 *   LabelCode::code128('LPN-WH1-26-000001', 90) -> ['svg' => <svg> Code 128, lebar modul tetap (mm), 'fits' => ...]
 *
 * Isi kode = teks polos yang sama dengan yang diketik/scan di aplikasi (nomor LPN / kode lokasi),
 * jadi scanner genggam, kamera HP (html5-qrcode: QR_CODE & CODE_128) dan ketik manual memberi hasil sama.
 */
final class LabelCode
{
    /** QR sebagai SVG; satu modul = 1 unit viewBox, dengan quiet zone 4 modul (standar). */
    public static function qr(string $text): string
    {
        $matrix = Encoder::encode($text, ErrorCorrectionLevel::M(), 'UTF-8')->getMatrix();
        $n = $matrix->getWidth();
        $q = 4;
        $size = $n + $q * 2;
        $path = '';
        for ($y = 0; $y < $n; $y++) {
            $run = 0;
            for ($x = 0; $x <= $n; $x++) {
                $on = $x < $n && $matrix->get($x, $y) === 1;
                if ($on) {
                    $run++;
                } elseif ($run > 0) {
                    $path .= 'M'.($x - $run + $q).' '.($y + $q).'h'.$run.'v1h-'.$run.'z';
                    $run = 0;
                }
            }
        }

        return '<svg class="lbl-qr" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$size.' '.$size.'" shape-rendering="crispEdges">'
            .'<rect width="100%" height="100%" fill="#fff"/><path d="'.$path.'" fill="#000"/></svg>';
    }

    /** Lebar satu garis (modul) yang dicoba, terbesar dulu. 0,25 mm = 2 titik printer 203 dpi (batas aman scanner). */
    public const MODULES_MM = [0.5, 0.4, 0.33, 0.25];

    /**
     * Code 128 sebagai SVG dengan lebar modul TETAP dalam mm (tidak ditarik), supaya setiap garis punya lebar
     * yang sama persis saat dicetak -> mudah dibaca scanner & kamera. Modul dipilih terbesar yang muat di $maxMm.
     * Bila tidak muat di 0,25 mm, barcode dipaksa muat ($fits = false) dan halaman cetak memberi peringatan.
     *
     * @return array{svg: string, width_mm: float, module_mm: float, fits: bool}
     */
    public static function code128(string $text, float $maxMm = 90): array
    {
        $barcode = (new TypeCode128())->getBarcode($text);
        $q = 10;   // quiet zone 10 modul kiri & kanan
        $modules = $barcode->getWidth() + $q * 2;
        $module = null;
        foreach (self::MODULES_MM as $mm) {
            if ($modules * $mm <= $maxMm) {
                $module = $mm;
                break;
            }
        }
        $fits = $module !== null;
        $width = $fits ? round($modules * $module, 2) : $maxMm;
        $x = $q;
        $rects = '';
        foreach ($barcode->getBars() as $bar) {
            $w = $bar->getWidth();
            if ($bar->isBar() && $w > 0) {
                $rects .= '<rect x="'.$x.'" y="0" width="'.$w.'" height="1"/>';
            }
            $x += $w;
        }
        $svg = '<svg class="lbl-bar" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$modules.' 1" preserveAspectRatio="none" shape-rendering="crispEdges" style="width:'.$width.'mm">'
            .'<rect width="100%" height="1" fill="#fff"/><g fill="#000">'.$rects.'</g></svg>';

        return ['svg' => $svg, 'width_mm' => $width, 'module_mm' => $fits ? $module : round($maxMm / $modules, 3), 'fits' => $fits];
    }

    /** Jumlah modul Code 128 (untuk memperkirakan lebar garis minimum di label kecil). */
    public static function code128Modules(string $text): int
    {
        return (new TypeCode128())->getBarcode($text)->getWidth() + 20;
    }
}
