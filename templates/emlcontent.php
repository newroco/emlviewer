<?php
/** @var array $_ */
?>
<p>From: <strong><?php p($_['from']) ?></strong></p>
<p>To: <strong><?php p($_['to']) ?></strong></p>
<p>Cc: <strong><?php p($_['cc']) ?></strong></p>
<p>Date received: <strong><?php p($_['date']) ?></strong></p>
<p>Subject: <strong><?php p($_['subject']) ?></strong></p>
<?php
if (!empty($_['attachments']) && count($_['attachments']) > 0) {
    echo '<p>Attachments: ';
    foreach ($_['attachments'] as $ind => $filename) {
        ?>
        <a href="<?php p($_['urlAttachment'] . $ind) ?>"><button type="button"><?php p($filename) ?></button></a>
        <?php
    }
    echo '</p>';
}
?>

<div class="buttonWrapper">
    <?php if (!empty($_['textContent'])) { ?>
        <button type="button" style="width: 15em;" id="toggle-text-content">Show raw text content</button>
    <?php } ?>
    <a href="<?php p($_['urlPdf']) ?>" id="make-pdf" target="_blank">
        <button type="button" style="width: 150px;">Download as PDF</button>
    </a>
    <a href="<?php p($_['urlPrinter']) ?>" id="printer-friendly" target="_blank">
        <button type="button" style="width: 250px;">Printer friendly version</button>
    </a>
</div>
<?php if (!empty($_['textContent'])) { ?>
    <div class="emlviewer_email_text_content fade-out">
        Message:<br/>
        <?php p($_['textContent']) ?>
    </div>
<?php } ?>
<div class="emlviewer-body">
    <div>Content:</div>
    <?php if (!empty($_['htmlContent'])) { ?>

        <iframe
                class="emlviewer_email_html_content"
                sandbox="allow-popups allow-popups-to-escape-sandbox"
                referrerpolicy="no-referrer"
                srcdoc="<?php p($_['htmlContent']) ?>"
        ></iframe>

    <?php } ?>
</div>
