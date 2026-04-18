<?php

namespace App\Commands;

use App\Traits\LaraKubeOutput;
use LaravelZero\Framework\Commands\Command;

class NodeCommand extends Command
{
    use LaraKubeOutput;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'node {commands* : The npm or node command to run} {--environment=local : The environment to target}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run npm or node commands inside the Kubernetes Node pod';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $command = implode(' ', $this->argument('commands'));

        $this->call('exec', [
            'commands' => [$command],
            '--service' => 'node',
            '--environment' => $this->option('environment'),
        ]);
    }
}
