<?php

namespace App\Traits;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

trait CheckPrerequisites
{
    /**
     * Check if the necessary tools are installed.
     */
    protected function checkPrerequisites(bool $requireK9s = false): bool
    {
        $missing = [];

        // 1. Check Docker Installation
        if (shell_exec('which docker') === null) {
            $missing[] = 'Docker (https://docs.docker.com/get-docker/)';
        }

        // 2. Check Kubectl Installation
        if (shell_exec('which kubectl') === null) {
            $missing[] = 'kubectl (https://kubernetes.io/docs/tasks/tools/)';
        }

        // 3. Check K9s (optional but recommended)
        if ($requireK9s && shell_exec('which k9s') === null) {
            warning('k9s is not installed. While not required for deployment, it is highly recommended for visualization.');
            info('Install it at: https://k9scli.io/topics/install/');
        }

        if (! empty($missing)) {
            error('The following prerequisites are missing from your system:');
            foreach ($missing as $item) {
                error("- {$item}");
            }

            return false;
        }

        // 4. Live Engine Check: Verify if Docker is actually RUNNING
        exec('docker info 2>&1', $output, $result);
        if ($result !== 0) {
            $this->laraKubeError('Docker engine is not running!');
            info('Please start OrbStack, Docker Desktop, or your local Docker daemon and try again.');

            return false;
        }

        return true;
    }
}
