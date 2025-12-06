<section class="page-intro">
    <h1>Branding / Letterhead</h1>
    <p>Configure the department letterhead used for letters, print views, and PDFs.</p>
</section>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?php echo yojaka_escape($message); ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo yojaka_escape($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<section class="panel">
    <h2>Letterhead Settings</h2>
    <form method="post" enctype="multipart/form-data" action="<?php echo yojaka_url('index.php?r=deptadmin/branding/letterhead'); ?>">
        <div class="form-group">
            <label for="department_name">Department name</label>
            <input type="text" id="department_name" name="department_name" value="<?php echo yojaka_escape($config['department_name'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label for="department_address">Department address</label>
            <textarea id="department_address" name="department_address" rows="3" placeholder="Address line 1&#10;Address line 2&#10;District, State, PIN"><?php echo yojaka_escape($config['department_address'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label for="header_html">Header HTML (optional)</label>
            <textarea id="header_html" name="header_html" rows="3" placeholder="<p>Additional header block</p>"><?php echo htmlspecialchars($config['header_html'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            <p class="help-text">HTML allowed. Keep it simple for printing and PDF output.</p>
        </div>

        <div class="form-group">
            <label for="footer_html">Footer HTML (optional)</label>
            <textarea id="footer_html" name="footer_html" rows="3" placeholder="<p>Footer note for printed letters</p>"><?php echo htmlspecialchars($config['footer_html'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            <p class="help-text">HTML allowed. Appears at the bottom of the letter.</p>
        </div>

        <div class="form-group">
            <label for="logo">Logo (optional)</label>
            <?php if (!empty($config['logo_file'])): ?>
                <div class="branding-logo-preview">
                    <?php if (!empty($logoDataUri)): ?>
                        <img src="<?php echo yojaka_escape($logoDataUri); ?>" alt="Current logo" style="max-height: 120px; max-width: 240px;">
                    <?php else: ?>
                        <p>No preview available for current logo.</p>
                    <?php endif; ?>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="remove_logo" value="1"> Remove current logo
                        </label>
                    </div>
                </div>
            <?php endif; ?>
            <input type="file" id="logo" name="logo" accept="image/*">
            <p class="help-text">Upload a PNG/JPG/GIF image for the department logo.</p>
        </div>

        <div class="form-actions">
            <button type="submit" class="button">Save Branding</button>
        </div>
    </form>
</section>

<section class="panel">
    <h2>Preview</h2>
    <div class="letter-preview" style="padding: 16px; border: 1px solid #ddd; background: #fff;">
        <?php if (!empty($previewHtml)): ?>
            <?php echo $previewHtml; ?>
        <?php else: ?>
            <p>Fill in the details above and save to see the preview.</p>
        <?php endif; ?>
    </div>
</section>
