<?php

declare(strict_types=1);

$nextcloud = getenv('NEXTCLOUD_PATH') ?: dirname(__DIR__, 3);
if (!is_file($nextcloud . '/lib/base.php')) {
    throw new RuntimeException('Set NEXTCLOUD_PATH to an installed, disposable Nextcloud test instance.');
}
define('OC_CONSOLE', true);
require_once $nextcloud . '/lib/base.php';
$appPath = \OCP\Server::get(\OCP\App\IAppManager::class)->getAppPath('emlviewer');
require_once $appPath . '/vendor/autoload.php';
