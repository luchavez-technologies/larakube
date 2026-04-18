<?php

namespace App\Commands;

use App\Traits\LaraKubeOutput;
use LaravelZero\Framework\Commands\Command;

class ArtisanCommand extends Command
{
    use LaraKubeOutput;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'art {commands* : The artisan command to run} {--environment=local : The environment to target}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run a php artisan command inside the cluster';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $artisanCommand = implode(' ', $this->argument('commands'));

        return $this->call('exec', [
            'commands' => ["php artisan {$artisanCommand}"],
            '--environment' => $this->option('environment'),
            '--service' => 'web',
        ]);
    }
}
