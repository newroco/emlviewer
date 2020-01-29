<?php
namespace OCA\EmlViewer\Migration;

require dirname(__FILE__)."/../../vendor/autoload.php";
use OCP\ILogger;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

use Composer\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

// putenv('COMPOSER_HOME=' . dirname(__FILE__) );

class InstallComposer implements IRepairStep {

      public function __construct(ILogger $log) {
        $this->log = $log;
      }

      /**
       * @param IOutput $output
       */
      public function run(IOutput $output) {
        $this->log->debug("bunicamea");
        try{
          $input = new ArrayInput(array('command' => 'install -d='.dirname(__FILE__).'/../../'));
          $application = new Application();
          $application->setAutoExit(false); // prevent `$application->run` method from exitting the script
          $application->run($input);
        }catch(\Exception $e){
          $this->log->debug($e->getMessage());
        }
        
      }
}