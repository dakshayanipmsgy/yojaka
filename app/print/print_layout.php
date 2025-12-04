<?php
require_once __DIR__ . '/../office.php';
require_once __DIR__ . '/../qr.php';

if (!function_exists('resolve_print_asset_src')) {
    function resolve_print_asset_src(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $candidate = $path;
        if (!file_exists($candidate)) {
            $candidate = YOJAKA_ROOT . '/' . ltrim($path, '/');
        }

        if (file_exists($candidate)) {
            $ext = strtolower((string) pathinfo($candidate, PATHINFO_EXTENSION));
            $mime = 'image/png';
            if (in_array($ext, ['jpg', 'jpeg'], true)) {
                $mime = 'image/jpeg';
            } elseif ($ext === 'gif') {
                $mime = 'image/gif';
            } elseif ($ext === 'svg') {
                $mime = 'image/svg+xml';
            } elseif ($ext === 'webp') {
                $mime = 'image/webp';
            }

            $data = @file_get_contents($candidate);
            if ($data !== false) {
                return 'data:' . $mime . ';base64,' . base64_encode($data);
            }
        }

        return YOJAKA_BASE_URL . '/' . ltrim($path, '/');
    }
}

if (!function_exists('print_qr_data_uri')) {
    function print_qr_data_uri(string $payload): ?string
    {
        if ($payload === '') {
            return null;
        }

        if (!function_exists('qr_generate_id')) {
            return null;
        }

        $qrPath = qr_generate_id($payload);
        if (!$qrPath || !is_file($qrPath)) {
            return null;
        }

        return resolve_print_asset_src($qrPath);
    }
}

if (!function_exists('render_print_page')) {
    function render_print_page(string $title, string $bodyHtml, string $officeId, ?string $watermarkOverride = null): void
    {
        $office = load_office_config_by_id($officeId);
        $print = office_print_config($officeId);

        $pageSize = strtoupper($print['page_size'] ?? 'A4');
        if (!in_array($pageSize, ['A4', 'LETTER'], true)) {
            $pageSize = 'A4';
        }
        $pageSizeCss = $pageSize === 'LETTER' ? 'Letter' : 'A4';

        $logoPath = $print['logo_path'] ?? null;
        $logoSrc = resolve_print_asset_src($logoPath);

        $headerHtml = $print['header_html'] ?? '';
        if (!$headerHtml && !empty($office['office_name'])) {
            $headerHtml = '<h2>' . htmlspecialchars($office['office_name'], ENT_QUOTES) . '</h2>';
        }
        $footerHtml = $print['footer_html'] ?? '';
        $watermarkText = $watermarkOverride ?? ($print['watermark_text'] ?? '');
        $showWatermark = ($print['watermark_enabled'] ?? false) && $watermarkText !== '';
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title><?php echo htmlspecialchars($title); ?></title>
            <link rel="stylesheet" href="<?php echo YOJAKA_BASE_URL; ?>/css/print.css">
            <style>
                @page { size: <?php echo $pageSizeCss; ?> portrait; margin: 15mm; }
            </style>
        </head>
        <body class="print-document">
            <?php if ($showWatermark): ?>
                <div class="watermark"><?php echo htmlspecialchars($watermarkText); ?></div>
            <?php endif; ?>

            <div class="document-page">
                <?php if (!empty($print['show_header'])): ?>
                    <div class="document-header">
                        <?php if ($logoSrc): ?>
                            <div class="document-header-logo">
                                <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="Logo">
                            </div>
                        <?php endif; ?>
                        <?php if ($headerHtml): ?>
                            <div class="document-header-text">
                                <?php echo $headerHtml; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="document-body">
                    <?php echo $bodyHtml; ?>
                </div>

                <?php if (!empty($print['show_footer'])): ?>
                    <div class="document-footer">
                        <?php echo $footerHtml; ?>
                    </div>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
    }
}
