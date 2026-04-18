<?php

namespace App\Commands;

use App\Traits\InteractsWithEnvironments;
use App\Traits\LaraKubeOutput;
use LaravelZero\Framework\Commands\Command;

class StartCommand extends Command
{
    use InteractsWithEnvironments, LaraKubeOutput;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'start {environment=local : The environment to start}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start all application pods (Scale to 1)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->renderHeader();

        $environment = $this->argument('environment');
        $namespace = $this->getNamespace($environment);

        $this->laraKubeInfo("Starting all pods in namespace '{$namespace}'...");

        $this->withSpin('Scaling deployments to 1...', function () use ($namespace) {
            exec("kubectl scale deployment --all --replicas=1 -n {$namespace} 2>/dev/null");
            exec("kubectl scale statefulset --all --replicas=1 -n {$namespace} 2>/dev/null");
        });

        $this->laraKubeInfo('All pods starting. Use "larakube dashboard" to monitor progress.');

        return 0;
    }
}
