<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ApplyHealingPatch extends Tool
{
    public function name(): string
    {
        return 'apply_healing_patch';
    }

    public function description(): string
    {
        return 'Surgically apply a Kubernetes manifest patch to fix a detected issue.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'filename' => $schema->string()
                ->description('The name of the patch file (e.g., fix-permissions-patch.yaml).'),
            'content' => $schema->string()
                ->description('The raw YAML content of the Kubernetes manifest.'),
            'environment' => $schema->string()
                ->description('The environment to target (local or production). Defaults to local.')
                ->default('local'),
        ];
    }

    public function handle(string $filename, string $content, string $environment = 'local'): Response
    {
        $projectPath = getcwd();
        $patchPath = "{$projectPath}/.infrastructure/k8s/overlays/{$environment}/{$filename}";

        if (! is_dir(dirname($patchPath))) {
            return Response::error("Environment directory '{$environment}' not found.");
        }

        file_put_contents($patchPath, $content);

        return Response::text("Patch '{$filename}' applied successfully to {$environment}. Run 'larakube up' to deploy the fix.");
    }
}
