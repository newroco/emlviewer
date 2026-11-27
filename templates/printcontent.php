<!DOCTYPE html>
<html lang="<?php p($l->getLanguageCode()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <link rel="icon" href="data:,">
    <title><?php p($_['subject'] ?? 'Email') ?></title>
    <link rel="stylesheet" href="<?php p($_['printStyle']) ?>">
    <script defer nonce="<?php p($_['cspNonce']) ?>" src="<?php p($_['printScript']) ?>"></script>
</head>
<body>
    <main class="emlviewer-print-page">
        <div class="emlviewer-print-toolbar">
            <button type="button" id="printButton">Print</button>
        </div>
        <?php include __DIR__ . '/email_headers.php'; ?>
        <article class="emlviewer-print-content">
            <?php print_unescaped($_['htmlContent']) ?>
        </article>
    </main>
</body>
</html>
