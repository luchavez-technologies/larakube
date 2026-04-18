<?php

namespace App\Commands;

use App\Traits\InteractsWithEnvironments;
use App\Traits\LaraKubeOutput;
use LaravelZero\Framework\Commands\Command;

class StopCommand extends Command
{
    use InteractsWithEnvironments, LaraKubeOutput;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stop {environment=local : The environment to stop}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stop all application pods without deleting data (Scale to 0)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->renderHeader();

        $environment = $this->argument('environment');
        $namespace = $this->getNamespace($environment);

        $this->laraKubeInfo("Stopping all pods in namespace '{$namespace}'...");

        $this->withSpin('Scaling deployments to 0...', function () use ($namespace) {
            exec("kubectl scale deployment --all --replicas=0 -n {$namespace} 2>/dev/null");
            exec("kubectl scale statefulset --all --replicas=0 -n {$namespace} 2>/dev/null");
        });

        $this->laraKubeInfo('All pods stopped. Your data remains safe in the cluster volumes.');
        $this->info('Next steps: larakube start');

        return 0;
    }
}
