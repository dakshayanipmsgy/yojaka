<?php
/** @var string $content */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yojaka</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f7f9fb; color: #333; }
        header { background: #0f4c81; color: #fff; padding: 16px 24px; }
        header h1 { margin: 0; font-size: 20px; letter-spacing: 1px; }
        main { padding: 24px; max-width: 900px; margin: 0 auto; }
        .card { background: #fff; border-radius: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); padding: 20px; }
        .tagline { color: #0f4c81; font-weight: bold; }
        .diagnostics { margin-top: 16px; }
        .diagnostics li { margin-bottom: 6px; }
        footer { margin-top: 40px; text-align: center; color: #888; font-size: 14px; }
    </style>
</head>
<body>
<header>
    <h1>Yojaka</h1>
</header>
<main>
    <div class="card">
        <?php echo $content; ?>
    </div>
    <footer>
        Initial skeleton ready for future modules.
    </footer>
</main>
</body>
</html>
