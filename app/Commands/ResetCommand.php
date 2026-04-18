<?php

namespace App\Commands;

use App\Traits\InteractsWithEnvironments;
use App\Traits\LaraKubeOutput;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\text;

class ResetCommand extends Command
{
    use InteractsWithEnvironments, LaraKubeOutput;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reset {environment=local : The environment to reset} {--force : Skip confirmation} {--image : Also delete the local Docker image}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Forcefully wipe all cluster state, local data, and optionally images (Factory Reset)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->renderHeader();

        $environment = $this->argument('environment');
        $namespace = $this->getNamespace($environment);
        $appName = basename(getcwd());

        if (! $this->option('force')) {
            $this->laraKubeError('NUCLEAR WARNING: This will WIPE all cluster state and local volume data.');
            $confirm = text(
                label: "To confirm, please type the project name '{$appName}':",
                required: true
            );

            if ($confirm !== $appName) {
                $this->laraKubeInfo('Confirmation failed. Reset cancelled.');

                return 0;
            }
        }

        $this->withSpin("Removing namespace '{$namespace}'...", function () use ($namespace) {
            exec("kubectl delete namespace {$namespace} --wait=false");
        });

        $this->withSpin('Forcefully removing cluster PersistentVolumes...', function () use ($appName) {
            exec("kubectl delete pv -l larakube-project={$appName} --force --grace-period=0");
        });

        $this->withSpin('Wiping local volume data on host...', function () {
            $volumePath = getcwd().'/.infrastructure/volume_data';
            if (is_dir($volumePath)) {
                exec("rm -rf {$volumePath}/*");
            }
        });

        if ($this->option('image')) {
            $this->withSpin("Deleting local Docker image '{$appName}:latest'...", function () use ($appName) {
                exec("docker rmi -f {$appName}:latest 2>/dev/null");
                exec("docker rmi -f {$appName}:local 2>/dev/null");
            });
        }

        $this->laraKubeInfo('Nuclear reset complete! Your project is now a clean slate.');
        $this->line('');
        $this->info('Next steps: larakube up');

        return 0;
    }
}
