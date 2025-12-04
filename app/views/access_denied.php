<?php
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - Yojaka</title>
    <link rel="stylesheet" href="<?= YOJAKA_BASE_URL; ?>/assets/css/style.css">
</head>
<body class="app-shell">
    <div class="access-denied">
        <h1>Access Denied</h1>
        <p>You do not have permission to view this page.</p>
        <a class="btn-primary" href="<?= YOJAKA_BASE_URL; ?>/app.php?page=dashboard">Return to dashboard</a>
    </div>
</body>
</html>
