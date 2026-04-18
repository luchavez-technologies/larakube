<?php

namespace App\Commands;

use App\Traits\InteractsWithEnvironments;
use App\Traits\LaraKubeOutput;
use LaravelZero\Framework\Commands\Command;

class ExecCommand extends Command
{
    use InteractsWithEnvironments, LaraKubeOutput;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exec {commands* : The command to run} 
                            {--environment=local : The environment to target} 
                            {--service=web : The service to target (web or node)}
                            {--user=www-data : The user to run the command as}';

    /**
     * The console command description.
     */
    protected $description = 'Execute a command inside a running pod';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $environment = $this->option('environment');
        $service = $this->option('service');
        $user = $this->option('user');
        $namespace = $this->getNamespace($environment);
        $command = implode(' ', $this->argument('commands'));

        $label = match ($service) {
            'node' => 'app=laravel-node',
            'web' => 'app=laravel-web',
            'horizon' => 'app=laravel-horizon',
            'reverb' => 'app=laravel-reverb',
            default => "app={$service}"
        };

        // Find the pod name
        $podName = shell_exec("kubectl get pods -n {$namespace} -l {$label} -o jsonpath='{.items[0].metadata.name}' 2>/dev/null");

        if (! $podName) {
            $this->laraKubeError("Could not find a running {$service} pod in namespace '{$namespace}'. Is the app running?");

            return 1;
        }

        $this->laraKubeInfo("Executing in {$podName} as {$user}...");

        // Execute the command
        passthru("kubectl exec -it -n {$namespace} -c php {$podName} -- su -s /bin/sh {$user} -c \"{$command}\"");

        return 0;
    }
}
