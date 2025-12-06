<section class="page-intro">
    <div class="panel-header">
        <h1>Edit RTI</h1>
        <div class="actions">
            <a class="button" href="<?php echo yojaka_url('index.php?r=rti/view&id=' . urlencode($record['id'] ?? '')); ?>">Back to case</a>
        </div>
    </div>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo yojaka_escape($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <form method="post">
        <div class="form-grid">
            <label>Received Date
                <input type="date" name="received_date" value="<?php echo yojaka_escape($basic['received_date'] ?? ''); ?>" required>
            </label>
            <label>RTI Number
                <input type="text" name="rti_number" value="<?php echo yojaka_escape($basic['rti_number'] ?? ''); ?>">
            </label>
            <label>Applicant Name
                <input type="text" name="applicant_name" value="<?php echo yojaka_escape($basic['applicant_name'] ?? ''); ?>" required>
            </label>
            <label>Applicant Address
                <textarea name="applicant_address" rows="3"><?php echo yojaka_escape($basic['applicant_address'] ?? ''); ?></textarea>
            </label>
            <label>Contact Details
                <input type="text" name="contact_details" value="<?php echo yojaka_escape($basic['contact_details'] ?? ''); ?>">
            </label>
            <label>Subject
                <input type="text" name="subject" value="<?php echo yojaka_escape($basic['subject'] ?? ''); ?>" required>
            </label>
            <label>Information Sought
                <textarea name="information_sought" rows="4" required><?php echo yojaka_escape($basic['information_sought'] ?? ''); ?></textarea>
            </label>
            <label>Mode Received
                <select name="mode_received">
                    <?php $modes = ['post' => 'Post', 'email' => 'Email', 'by_hand' => 'By Hand', 'online' => 'Online']; ?>
                    <?php foreach ($modes as $value => $label): ?>
                        <option value="<?php echo yojaka_escape($value); ?>" <?php echo (($basic['mode_received'] ?? '') === $value) ? 'selected' : ''; ?>><?php echo yojaka_escape($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Fee Details
                <input type="text" name="fee_details" value="<?php echo yojaka_escape($basic['fee_details'] ?? ''); ?>">
            </label>
            <label>Internal Remarks
                <textarea name="remarks" rows="3"><?php echo yojaka_escape($basic['remarks'] ?? ''); ?></textarea>
            </label>
        </div>
        <button type="submit">Save Changes</button>
    </form>
</section>
