<?php

namespace App\Commands;

use App\Actions\GarageAction;
use App\Actions\MinioAction;
use App\Actions\SeaweedFsAction;
use App\Enums\Blueprint;
use App\Enums\DatabaseEngine;
use App\Enums\LaravelFeature;
use App\Enums\ObjectStorage;
use App\Traits\CheckPrerequisites;
use App\Traits\GeneratesProjectInfrastructure;
use App\Traits\InteractsWithDocker;
use App\Traits\InteractsWithProjectConfig;
use App\Traits\LaraKubeOutput;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

class AddCommand extends Command
{
    use CheckPrerequisites, GeneratesProjectInfrastructure, InteractsWithDocker, InteractsWithProjectConfig, LaraKubeOutput;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'add {items?* : The database(s), feature(s), blueprint, or storage to add}';

    /**
     * The console command description.
     */
    protected $description = 'Add databases, Laravel features, blueprints, or storage to an existing project';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->renderHeader();

        if (! $this->checkPrerequisites()) {
            return 1;
        }

        $projectPath = getcwd();
        if (! is_dir($projectPath.'/.infrastructure')) {
            $this->laraKubeError('Not a LaraKube project. Make sure you are in the root directory.');

            return 1;
        }

        $config = $this->getProjectConfig($projectPath);
        $appName = basename($projectPath);
        $k8sPath = $projectPath.'/.infrastructure/k8s';

        $selectedItems = $this->argument('items');

        if (empty($selectedItems)) {
            $currentBlueprint = $config['blueprint'] ?? Blueprint::LARAVEL->value;
            $currentDbs = $config['databases'] ?? [];
            $currentFeatures = $config['features'] ?? [];
            $currentStorage = $config['objectStorage'] ?? 'none';

            $options = array_merge(
                collect(Blueprint::cases())
                    ->filter(fn ($c) => $c->value !== $currentBlueprint)
                    ->map(fn ($c) => "Blueprint: {$c->value}")->all(),
                collect(DatabaseEngine::cases())
                    ->filter(fn ($c) => ! in_array($c->value, $currentDbs))
                    ->map(fn ($c) => "DB: {$c->value}")->all(),
                collect(ObjectStorage::cases())
                    ->filter(fn ($c) => $c->name !== $currentStorage)
                    ->map(fn ($c) => "Storage: {$c->value}")->all(),
                collect(LaravelFeature::cases())
                    ->filter(fn ($c) => ! in_array($c->value, $currentFeatures))
                    ->map(fn ($c) => "Feature: {$c->value}")->all()
            );

            if (empty($options)) {
                $this->laraKubeInfo('Your project already has all available features, databases, and storage installed!');

                return 0;
            }

            $selected = multiselect(
                label: 'What would you like to add?',
                options: $options,
                required: true
            );

            $selectedItems = collect($selected)->map(fn ($item) => str_replace(['Blueprint: ', 'DB: ', 'Storage: ', 'Feature: '], '', $item))->toArray();
        }

        foreach ($selectedItems as $itemName) {
            // 1. Detect if it's a Blueprint
            $blueprint = Blueprint::tryFrom($itemName);
            if ($blueprint) {
                if (($config['blueprint'] ?? null) === $blueprint->value) {
                    $this->laraKubeInfo("Project is already using the '{$blueprint->value}' blueprint. Skipping...");

                    continue;
                }
                $this->addBlueprint($blueprint, $projectPath, $k8sPath, $appName, $config);

                continue;
            }

            // 2. Detect if it's a Database
            $dbEngine = DatabaseEngine::tryFrom($itemName);
            if ($dbEngine) {
                if (in_array($dbEngine->value, $config['databases'] ?? [])) {
                    $this->laraKubeInfo("Database '{$dbEngine->value}' is already added to this project. Skipping...");

                    continue;
                }
                $this->addDatabase($dbEngine, $projectPath, $k8sPath, $appName, $config);

                continue;
            }

            // 3. Detect if it's Object Storage
            $storage = ObjectStorage::tryFrom($itemName);
            if ($storage) {
                if (($config['objectStorage'] ?? null) === $storage->name) {
                    $this->laraKubeInfo("Storage '{$storage->value}' is already added to this project. Skipping...");

                    continue;
                }
                $this->addStorage($storage, $projectPath, $k8sPath, $appName, $config);

                continue;
            }

            // 4. Detect if it's a Feature
            $feature = LaravelFeature::tryFrom($itemName);
            if ($feature) {
                if (in_array($feature->value, $config['features'] ?? [])) {
                    $this->laraKubeInfo("Feature '{$feature->value}' is already added to this project. Skipping...");

                    continue;
                }
                $this->addFeature($feature, $projectPath, $k8sPath, $appName, $config);

                continue;
            }

            $this->laraKubeError("Could not find blueprint, database, storage or feature matching '{$itemName}'. Skipping...");
        }

        return 0;
    }

    protected function addDatabase(DatabaseEngine $engine, string $projectPath, string $k8sPath, string $appName, array $config): void
    {
        $this->withSpin("Adding database '{$engine->value}' to cluster manifests...", function () use ($engine, $k8sPath, $appName, $projectPath) {
            if ($action = $engine->action()) {
                $action->updateK8s($k8sPath, $appName, ['projectPath' => $projectPath]);
                $action->updateDockerCompose($projectPath);
            }
        });

        $this->updateProjectConfig($projectPath, 'databases', [$engine->value]);

        if ($engine !== DatabaseEngine::REDIS && $engine !== DatabaseEngine::SQLITE) {
            if (confirm("Would you like to make {$engine->value} your primary database connection? (Updates your .env)", true)) {
                $this->updateEnvironmentDatabase($projectPath, $engine);
            }
        }

        $this->laraKubeInfo("Database '{$engine->value}' added successfully!");
    }

    protected function addBlueprint(Blueprint $blueprint, string $projectPath, string $k8sPath, string $appName, array &$config): void
    {
        $this->laraKubeInfo("Applying blueprint '{$blueprint->value}' to project '{$appName}'...");

        if ($blueprintAction = $blueprint->action()) {
            $blueprintConfig = $blueprintAction->gatherConfig();
            $config = array_merge($config, $blueprintConfig);

            // 1. Merge and persist PHP extensions in config first
            $blueprintExtensions = $blueprintAction->getPhpExtensions();
            $currentExts = $config['additionalExtensions'] ?? [];
            $allExtensions = array_unique(array_merge($currentExts, $blueprintExtensions));
            $config['additionalExtensions'] = $allExtensions;

            // 2. Persist updated config so subsequent calls use it
            $config['blueprint'] = $blueprint->value;
            $this->updateProjectConfig($projectPath, 'blueprint', $blueprint->value);
            $this->updateProjectConfig($projectPath, 'additionalExtensions', $allExtensions);

            // 3. Apply blueprint infrastructure
            $this->withSpin("Applying blueprint '{$blueprint->value}' to cluster manifests...", function () use ($blueprintAction, $projectPath, $k8sPath, $appName, $config) {
                $blueprintAction->apply($projectPath, $k8sPath, $appName, $config);
            });

            // 4. Regenerate Dockerfile.php with the merged extension list
            $this->withSpin('Updating Dockerfile.php with required extensions...', function () use ($projectPath, $config, $allExtensions) {
                $this->generateDockerfiles($projectPath, $config['serverVariation'], $config['phpVersion'], $config['os'], $allExtensions);
            });

            // 5. Build local image
            $this->buildImage($projectPath, $appName);

            // 6. Install packages
            $this->installLaravelFeatures($projectPath, [], $config['packageManager'] ?? 'npm', array_merge($config, ['blueprint' => $blueprint->value]));
        }

        $this->laraKubeInfo("Blueprint '{$blueprint->value}' applied successfully!");

        if ($instructions = $blueprintAction->getPostInstallInstructions()) {
            $this->line('');
            $this->warning('Blueprint Next Steps:');
            foreach ($instructions as $line) {
                $this->line("  {$line}");
            }
        }
    }

    protected function addStorage(ObjectStorage $storage, string $projectPath, string $k8sPath, string $appName, array &$config): void
    {
        $action = match ($storage) {
            ObjectStorage::MINIO => new MinioAction,
            ObjectStorage::SEAWEEDFS => new SeaweedFsAction,
            ObjectStorage::GARAGE => new GarageAction,
        };

        $this->withSpin("Adding storage '{$storage->value}' to cluster manifests...", function () use ($action, $k8sPath, $appName, $projectPath) {
            $action->updateK8s($k8sPath, $appName, ['projectPath' => $projectPath]);
        });

        // Use shared trait for installation
        $this->installLaravelFeatures($projectPath, [], $config['packageManager'] ?? 'npm', $config);

        // Run onPostInstall to update .env
        $action->onPostInstall($projectPath);

        $this->updateProjectConfig($projectPath, 'objectStorage', $storage->name);

        $this->laraKubeInfo("Storage '{$storage->value}' added successfully!");
    }

    protected function addFeature(LaravelFeature $feature, string $projectPath, string $k8sPath, string $appName, array $config): void
    {
        $action = $feature->action();

        $this->withSpin("Adding feature '{$feature->value}' to cluster manifests...", function () use ($action, $k8sPath, $appName, $projectPath) {
            $action->updateK8s($k8sPath, $appName, [
                'projectPath' => $projectPath,
            ]);

            $action->updateDockerCompose($projectPath);
        });

        // Use shared trait for installation
        $this->installLaravelFeatures($projectPath, [$feature->value], $config['packageManager'] ?? 'npm', $config);

        $this->updateProjectConfig($projectPath, 'features', [$feature->value]);

        $this->laraKubeInfo("Feature '{$feature->value}' added successfully!");
    }

    protected function updateEnvironmentDatabase(string $projectPath, DatabaseEngine $engine): void
    {
        $dbHost = $engine->dbHost();
        $dbUser = $engine->dbUsername();
        $dbPort = $engine->dbPort();
        $dbConn = $engine->dbConnection();

        $this->syncEnvFile($projectPath, [
            'DB_CONNECTION' => $dbConn,
            'DB_HOST' => $dbHost,
            'DB_PORT' => $dbPort,
            'DB_USERNAME' => $dbUser,
        ]);
    }
}
