<?php

namespace App\Commands;

use App\Traits\InteractsWithHosts;
use App\Traits\LaraKubeOutput;
use LaravelZero\Framework\Commands\Command;

class HostsCommand extends Command
{
    use InteractsWithHosts, LaraKubeOutput;

    protected $signature = 'hosts';

    protected $description = 'Manage /etc/hosts entries for the local environment';

    public function handle(): int
    {
        $this->renderHeader();
        $this->ensureHostsAreSet();

        return 0;
    }
}
