<?php

namespace App\Commands;

use App\Traits\CheckPrerequisites;
use App\Traits\GathersInfrastructureConfig;
use App\Traits\GeneratesProjectInfrastructure;
use App\Traits\InteractsWithDocker;
use App\Traits\InteractsWithProjectConfig;
use App\Traits\LaraKubeOutput;
use LaravelZero\Framework\Commands\Command;
use Random\RandomException;

use function Laravel\Prompts\info;

class InitCommand extends Command
{
    use CheckPrerequisites, GathersInfrastructureConfig, GeneratesProjectInfrastructure, InteractsWithDocker, InteractsWithProjectConfig, LaraKubeOutput;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize LaraKube for an existing Laravel project';

    /**
     * Execute the console command.
     *
     * @throws RandomException
     */
    public function handle(): int
    {
        $this->renderHeader();

        if (! $this->checkPrerequisites()) {
            return 1;
        }

        $projectPath = getcwd();
        $appName = basename($projectPath);

        $this->laraKubeInfo("Initializing LaraKube for project: {$appName}...");

        $config = $this->gatherConfig();

        $installFeatures = false;
        if (! empty($config['features'])) {
            $installFeatures = $this->confirm('Would you like to install the selected Laravel features now?', true);
        }

        $this->orchestrateProjectScaffolding($projectPath, $appName, $config, $installFeatures);

        $this->laraKubeInfo("LaraKube initialized successfully for {$appName}!");
        info('Next steps: larakube up');

        return 0;
    }
}
