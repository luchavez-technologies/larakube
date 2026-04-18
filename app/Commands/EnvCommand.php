<?php

namespace App\Commands;

use App\Traits\LaraKubeOutput;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\text;

class EnvCommand extends Command
{
    use LaraKubeOutput;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'env {name? : The name of the new environment}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Kubernetes environment overlay';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $envName = $this->argument('name') ?? text(
            label: 'What is the name of the new environment?',
            placeholder: 'staging',
            required: true
        );

        $appName = basename(getcwd());
        $projectPath = getcwd();
        $baseOverlayPath = "{$projectPath}/.infrastructure/k8s/overlays";
        $newEnvPath = "{$baseOverlayPath}/{$envName}";

        if (! is_dir("{$baseOverlayPath}/production")) {
            $this->laraKubeError('Base production environment not found. Are you in a LaraKube project?');

            return 1;
        }

        if (is_dir($newEnvPath)) {
            $this->laraKubeError("Environment '{$envName}' already exists.");

            return 1;
        }

        $this->laraKubeInfo("Creating environment '{$envName}'...");

        @mkdir($newEnvPath, 0755, true);

        // 1. Create .env.{env} file
        $newEnvFile = ".env.{$envName}";
        if (! file_exists($projectPath.'/'.$newEnvFile)) {
            copy($projectPath.'/.env', $projectPath.'/'.$newEnvFile);
            $this->laraKubeInfo("Created {$newEnvFile}");
        }

        // 2. Update .gitignore
        $gitignorePath = $projectPath.'/.gitignore';
        if (file_exists($gitignorePath)) {
            $gitignore = file_get_contents($gitignorePath);
            if (! str_contains($gitignore, '.env.*')) {
                $gitignore .= "\n.env.*\n";
                file_put_contents($gitignorePath, $gitignore);
                $this->laraKubeInfo('Updated .gitignore to exclude .env.* files');
            }
        }

        // Copy from production as a safe base
        $files = ['kustomization.yaml', 'namespace.yaml', 'deployment-patch.yaml'];
        foreach ($files as $file) {
            $content = file_get_contents("{$baseOverlayPath}/production/{$file}");

            // Update the namespace in the new files
            $oldNamespace = "{$appName}-production";
            $newNamespace = "{$appName}-{$envName}";
            $content = str_replace($oldNamespace, $newNamespace, $content);

            file_put_contents("{$newEnvPath}/{$file}", $content);
        }

        $this->laraKubeInfo("Environment '{$envName}' created successfully at .infrastructure/k8s/overlays/{$envName}");
    }
}
