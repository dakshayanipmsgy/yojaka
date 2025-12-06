<section class="error">
    <h1>Something went wrong</h1>
    <p><?php echo isset($message) ? yojaka_escape($message) : 'An unexpected error occurred.'; ?></p>
    <p><a href="<?php echo yojaka_url('index.php'); ?>">Return home</a></p>
</section>
