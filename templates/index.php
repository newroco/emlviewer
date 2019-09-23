<?php
$eventDispatcher = \OC::$server->getEventDispatcher();
$eventDispatcher->addListener('OCA\Files::loadAdditionalScripts', function() {
	 script('emlviewer', 'script');
});

script('emlviewer', 'script');
style('emlviewer', 'style');
?>

