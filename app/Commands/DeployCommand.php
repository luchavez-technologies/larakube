<?php

namespace App\Commands;

use App\Traits\InteractsWithEnvironments;
use App\Traits\LaraKubeOutput;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;

class DeployCommand extends Command
{
    use InteractsWithEnvironments, LaraKubeOutput;

    protected $signature = 'deploy {environment? : The environment to deploy to}';

    protected $description = 'Build and deploy the application to a remote environment';

    public function handle(): int
    {
        $this->renderHeader();

        $environment = $this->argument('environment') ?? 'production';

        if ($environment === 'local') {
            $this->laraKubeInfo("For local development, please use 'larakube up'.");

            return 0;
        }

        $this->laraKubeInfo("Starting deployment to '{$environment}'...");

        // 1. Verify kubectl context
        $context = shell_exec('kubectl config current-context');
        $this->laraKubeInfo('Current Kubernetes Context: '.trim($context));

        if (! confirm('Are you sure you want to deploy to this cluster?')) {
            $this->laraKubeInfo('Deployment cancelled.');

            return 0;
        }

        // 2. Offer to build and push
        if (confirm('Would you like to build and push the production image now?')) {
            $appName = basename(getcwd());
            // In a real scenario, we'd ask for the registry URL (e.g., ghcr.io/user/repo)
            $this->laraKubeInfo("Note: This assumes you have 'docker login' configured for your registry.");

            // For now, we delegate to the 'up' logic but ensure it targets production
            $this->call('up', ['environment' => $environment]);
        } else {
            // Just apply manifests
            $this->call('up', ['environment' => $environment]);
        }

        return 0;
    }
}
