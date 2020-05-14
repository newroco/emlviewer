<?php

namespace OCA\EmlViewer\AppInfo;

use OCP\AppFramework\App;
use OCP\Util;
use \OCA\EmlViewer\Storage\AuthorStorage;

class Application extends App {

    const APP_ID = 'emlviewer';

    public function __construct() {
        parent::__construct(self::APP_ID);

        $manager = \OC::$server->getContentSecurityPolicyManager();
        $policy = new \OCP\AppFramework\Http\EmptyContentSecurityPolicy();

        $policy->addAllowedStyleDomain('\'self\'');
        $policy->addAllowedScriptDomain('\'self\'');

        $policy->addAllowedImageDomain('*');
        $policy->addAllowedImageDomain('data:');
        $policy->addAllowedImageDomain('blob:');

        $policy->addAllowedMediaDomain('\'self\'');
        $policy->addAllowedMediaDomain('blob:');

        $policy->addAllowedChildSrcDomain('\'self\'');

        $policy->addAllowedConnectDomain('\'self\'');

        $manager->addDefaultPolicy($policy);

        /**
         * Storage Layer
         */
        $container = $this->getContainer();
        $container->registerService('AuthorStorage', function($c) {
            return new AuthorStorage($c->query('RootStorage'));
        });

        $container->registerService('RootStorage', function($c) {
            return $c->query('ServerContainer')->getUserFolder();
        });

        $manager = \OC::$server->getContentSecurityPolicyManager();

    }
    public function register(){
        $this->registerScripts();
    }


    protected function registerScripts()
    {
        $eventDispatcher = \OC::$server->getEventDispatcher();
        $eventDispatcher->addListener('OCA\Files::loadAdditionalScripts', function() {
            script(self::APP_ID, 'script');
            style(self::APP_ID, 'style');
        });
    }
}