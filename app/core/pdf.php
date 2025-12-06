<?php
// PDF rendering helper for Yojaka.

function yojaka_pdf_library_path(): string
{
    return yojaka_config('paths.app_path') . '/lib/dompdf/autoload.inc.php';
}

function yojaka_pdf_is_available(): bool
{
    return file_exists(yojaka_pdf_library_path());
}

function yojaka_pdf_render_html_to_pdf(string $html, string $outputPath): bool
{
    if (!yojaka_pdf_is_available()) {
        return false;
    }

    $libraryPath = yojaka_pdf_library_path();
    require_once $libraryPath;

    if (!class_exists('Dompdf\Dompdf')) {
        return false;
    }

    $dompdf = new Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $output = $dompdf->output();
    if ($output === false) {
        return false;
    }

    return file_put_contents($outputPath, $output) !== false;
}
