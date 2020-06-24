﻿<?php
    /** @var array $_ */
?>
<p>From: <strong><?php p($_['from']) ?></strong></p>
<p>To: <strong><?php p($_['to']) ?></strong></p>
<p>Date received: <strong><?php p($_['date']) ?></strong></p>
<p>Subject: <strong><?php p($_['subject']) ?></strong></p>
<?php
if(!empty($_['attachments']) && count($_['attachments']) > 0) {
    echo '<p>Attachments: ';
    foreach($_['attachments'] as $ind => $filename){
        echo '<a class="attachment" href="'.$_['urlAttachment'].$ind.'">'.$filename.'</a>';
    }
    echo '</p>';
}

if(!empty($_['htmlContent'])) { ?>
    <div class="buttonWrapper">
        <button type="button" style="width: 150px;" id="toggle-text-content">Show raw content</button>
        <a href="<?php p($_['urlPdf']) ?>" id="make-pdf" target="_blank"><button type="button" style="width: 150px;" >Download as PDF</button></a>
        <a href="<?php p($_['urlPrinter']) ?>" id="printer-friendly" target="_blank"><button type="button" style="width: 250px;" >Printer friendly version</button></a>
    </div>
    <div id="email-text-content" class="fade-out">
        Message:<br/>
        <?php p($_['textContent']) ?>
    </div>
<?php } ?>
<?php if(!empty($_['htmlContent'])) { ?>
<div style="flex: 1;">Content:<br/>
    <iframe
        id="email-html-content"
        srcdoc="<?php p($_['htmlContent']) ?>"
    ></iframe>
</div>
<?php } ?>