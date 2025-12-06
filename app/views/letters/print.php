<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Letter <?php echo yojaka_escape($record['id'] ?? ''); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; color: #111; }
        .yojaka-letter { max-width: 800px; margin: 0 auto; }
        .yojaka-letter p { line-height: 1.6; }
        @media print {
            .print-note { display: none; }
        }
    </style>
</head>
<body>
    <div class="print-note">
        <p><strong>Print Preview:</strong> Use your browser print dialog to print or save as PDF.</p>
        <hr>
    </div>
    <div class="yojaka-letter">
        <?php echo $renderedHtml; ?>
    </div>
</body>
</html>
