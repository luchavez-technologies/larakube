<?php

namespace App\Commands;

use App\Traits\InteractsWithEnvironments;
use App\Traits\LaraKubeOutput;
use LaravelZero\Framework\Commands\Command;

class DashboardCommand extends Command
{
    use InteractsWithEnvironments, LaraKubeOutput;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashboard {environment? : The environment to monitor (local or production)} {--simple : Use simple kubectl view instead of k9s}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Open a dashboard to monitor your Kubernetes cluster';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $environment = $this->argument('environment') ?? 'local';
        $namespace = $this->getNamespace($environment);

        $hasK9s = shell_exec('which k9s') !== null;
        $hasWatch = shell_exec('which watch') !== null;

        // Best experience: K9s
        if (! $this->option('simple') && $hasK9s) {
            $this->laraKubeInfo("Launching K9s for namespace: {$namespace}...");
            passthru("k9s -n {$namespace}");

            return 0;
        }

        // Fallback or Simple experience
        $this->laraKubeInfo("Monitoring namespace: {$namespace} (Live View)");

        $isLinux = PHP_OS_FAMILY === 'Linux';
        $watchCmd = $isLinux ? 'sudo apt install watch' : 'brew install watch';
        $k9sCmd = $isLinux ? 'snap install k9s' : 'brew install k9s';

        if (! $hasK9s) {
            $this->warn("  💡 TIP: For a much better experience, install K9s: {$k9sCmd}");
        }

        if (! $hasWatch) {
            $this->warn("  💡 TIP: For a smoother live view, install watch: {$watchCmd}");
        }

        $this->info('  Press Ctrl+C to stop.');
        $this->line('');

        if ($hasWatch) {
            passthru("watch -n 1 kubectl get pods -n {$namespace}");
        } else {
            // Jarring fallback loop
            while (true) {
                passthru('clear');
                $this->laraKubeInfo("Monitoring namespace: {$namespace} (Live View)");
                $this->warn("  TIP: install 'watch' ({$watchCmd}) to stop the blinking.");
                $this->line('');
                passthru("kubectl get pods -n {$namespace}");
                sleep(1);
            }
        }

        return 0;
    }
}
