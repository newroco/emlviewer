<?php

namespace OCA\EmlViewer\Migration;

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\ILogger;

class SetupUpdateStep implements IRepairStep
{

    /** @var ILogger */
    protected $logger;

    public function __construct(ILogger $logger){
        $this->logger = $logger;
    }

    /**
     * Returns the step's name
     */
    public function getName() {
        return 'Setup Step - install or run composer!';
    }

    /**
     * @param IOutput $output
     */
    public function run(IOutput $output)
    {
        $appName = "Eml Viewer";
        $output->info("This step will take a few seconds.");
        $output->startProgress(2);
        try {
            $out = shell_exec(__DIR__.'/install_composer.sh 2>&1');
            $this->logger->warning($out, ["app" => $appName]);
            $out = shell_exec('php '.__DIR__.'/bin/composer update 2>&1');
            $this->logger->warning($out, ["app" => $appName]);
        } catch (\Exception $e) {
            $this->logger->warning($e->getMessage(), ["app" => $appName]);
        }
        $output->finishProgress();
    }
}