<?php

namespace OCA\EmlViewer\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\Util;
use \OCA\EmlViewer\Storage\AuthorStorage;

class Application extends App {

    const APP_ID = 'emlviewer';

    public function __construct() {
        parent::__construct(self::APP_ID);

        $manager = \OC::$server->getContentSecurityPolicyManager();
        $policy = new EmptyContentSecurityPolicy();

        $policy->addAllowedStyleDomain('\'self\'');
        $policy->addAllowedScriptDomain('\'self\'');

        $policy->addAllowedImageDomain('*');
        $policy->addAllowedImageDomain('data:');
        $policy->addAllowedImageDomain('blob:');
        $policy->addAllowedImageDomain('cid:');

        $policy->addAllowedMediaDomain('\'self\'');
        $policy->addAllowedMediaDomain('blob:');

        $policy->addAllowedChildSrcDomain('\'self\'');
        $policy->addAllowedChildSrcDomain('blob:');

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