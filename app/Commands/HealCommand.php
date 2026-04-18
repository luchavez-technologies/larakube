<?php

namespace App\Commands;

use App\Traits\GeneratesProjectInfrastructure;
use App\Traits\InteractsWithProjectConfig;
use App\Traits\LaraKubeOutput;
use LaravelZero\Framework\Commands\Command;

class HealCommand extends Command
{
    use GeneratesProjectInfrastructure, InteractsWithProjectConfig, LaraKubeOutput;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'heal';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automated self-healing: Regenerate project infrastructure from .larakube.json';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->renderHeader();

        $projectPath = getcwd();
        $config = $this->getProjectConfig($projectPath);

        if (empty($config)) {
            $this->laraKubeError('No LaraKube configuration found (.larakube.json)!');
            $this->info('Make sure you are in the root of a LaraKube project.');

            return 1;
        }

        $appName = basename($projectPath);
        $this->laraKubeInfo("Healing infrastructure for masterpiece: {$appName}...");

        $this->withSpin('Regenerating Kubernetes manifests and patches...', function () use ($projectPath, $appName, $config) {
            // We pass false for both installFeatures and buildImage because we only want to fix the K8s files
            $this->orchestrateProjectScaffolding($projectPath, $appName, $config, false, false);
        });

        $this->laraKubeInfo('ARCHITECTURAL INTEGRITY RESTORED! ✅');
        $this->info('Next steps: larakube up --dry-run');

        $this->renderStarPrompt();

        return 0;
    }
}
