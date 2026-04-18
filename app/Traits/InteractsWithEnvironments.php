<?php

namespace App\Traits;

use function Laravel\Prompts\select;

trait InteractsWithEnvironments
{
    /**
     * Get the available Kubernetes environment overlays.
     */
    protected function getAvailableEnvironments(): array
    {
        $overlayPath = getcwd().'/.infrastructure/k8s/overlays';

        if (! is_dir($overlayPath)) {
            return ['local', 'production'];
        }

        return array_values(array_diff(scandir($overlayPath), ['.', '..']));
    }

    /**
     * Prompt the user to select an environment.
     */
    protected function askForEnvironment(string $label = 'Which environment would you like to target?', string $default = 'local'): string
    {
        return select(
            label: $label,
            options: $this->getAvailableEnvironments(),
            default: $default
        );
    }

    /**
     * Get the Kubernetes namespace for a given environment.
     */
    protected function getNamespace(string $environment): string
    {
        $appName = basename(getcwd());

        return "{$appName}-{$environment}";
    }
}
