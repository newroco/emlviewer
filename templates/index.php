<?php

declare(strict_types=1);

use OCP\Util;

    $appId = OCA\EmlViewer\AppInfo\Application::APP_ID;
    $eventDispatcher = \OC::$server->get(IEventDispatcher::class);

    $eventDispatcher->addListener(LoadAdditionalScriptsEvent::class, function () {
    Util::addScript($appId, 'script');
    Util::addStyle($appId, 'style');
    })
?>

<div id="app-content">
    <div id="emlviewer"></div>
</div>
