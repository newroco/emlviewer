﻿<?php
    /** @var array $_ */
    /** @var OCP\IURLGenerator $urlGenerator */
?>
<p>From: <strong><?php p($_['from']) ?></strong></p>
<p>To: <strong><?php p($_['to']) ?></strong></p>
<p>Date received: <strong><?php p($_['date']) ?></strong></p>
<?php if(!empty($_['textContent'])) { ?>
    <button type="button" style="width: 150px;" id="toggle-text-content">Show raw content</button>
    <a href="#" id="make-pdf"><button type="button" style="width: 150px;" >Download as PDF</button></a>
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
        style="width: 100%;min-height: 200px;height: 100%;"
    ></iframe>
</div>
<?php } ?>