<?php
namespace OCA\EmlViewer\Migration;

require dirname(__FILE__)."/../../vendor/autoload.php";

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

use Composer\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

putenv('COMPOSER_HOME=' . __DIR__ . '/vendor/bin/composer');

class InstallComposer implements IRepairStep {

      public function __construct() {
      }

      /**
       * @param IOutput $output
       */
      public function run(IOutput $output) {
        $input = new ArrayInput(array('command' => 'install'));
        $application = new Application();
        $application->setAutoExit(false); // prevent `$application->run` method from exitting the script
        $application->run($input);
      }
}