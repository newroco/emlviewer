<table id="email-headers" >
    <tr><td style="text-align:left;padding: 2px 5px; font-size: 14px;" align="left">
        <p>From: <strong><?php p($_['from']) ?></strong></p>
        <p>To: <strong><?php p($_['to']) ?></strong></p>
        <p>Date received: <strong><?php p($_['date']) ?></strong></p>
        <p>Subject: <strong><?php p($_['subject']) ?></strong></p>
    </td></tr>
</table>
<!-- making sure this is displayed only on pdf output -->
<hr style="display: none"/>