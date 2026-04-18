<?php

namespace App\Traits;

trait InteractsWithProjectConfig
{
    /**
     * Get the project configuration from .larakube.json.
     */
    protected function getProjectConfig(string $projectPath): array
    {
        $path = $projectPath.'/.larakube.json';
        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true) ?? [];
        }

        return [];
    }

    /**
     * Update a specific key in the project configuration.
     */
    protected function updateProjectConfig(string $projectPath, string $key, mixed $value): void
    {
        $config = $this->getProjectConfig($projectPath);

        if (is_array($value) && isset($config[$key]) && is_array($config[$key])) {
            $config[$key] = array_unique(array_merge($config[$key], $value));
        } else {
            $config[$key] = $value;
        }

        file_put_contents(
            $projectPath.'/.larakube.json',
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}
