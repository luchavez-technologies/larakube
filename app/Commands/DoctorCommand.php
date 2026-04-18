<?php

namespace App\Commands;

use App\Traits\InteractsWithEnvironments;
use App\Traits\InteractsWithProjectConfig;
use App\Traits\LaraKubeOutput;
use LaravelZero\Framework\Commands\Command;

class DoctorCommand extends Command
{
    use InteractsWithEnvironments, InteractsWithProjectConfig, LaraKubeOutput;

    protected $signature = 'doctor {--environment=local : The environment to diagnose}';

    protected $description = 'Diagnose project and cluster health issues';

    public function handle()
    {
        $this->renderHeader();
        $this->laraKubeInfo('Starting LaraKube Health Check...');

        $this->checkPrerequisites();
        $this->checkLocalEnvironment();
        $this->checkManifestIntegrity();
        $this->checkHostsFile();
        $this->checkPortConflicts();
        $this->checkClusterConnectivity();
        $this->checkPodHealth();

        $this->line('');
        $this->laraKubeInfo('Diagnostic complete!');

        return 0;
    }

    protected function checkPrerequisites()
    {
        $this->task('Checking Prerequisites', function () {
            $docker = shell_exec('docker --version');
            $kubectl = shell_exec('kubectl version --client');

            if (! $docker || ! $kubectl) {
                return false;
            }

            return true;
        });
    }

    protected function checkLocalEnvironment()
    {
        $this->task('Checking .env configuration', function () {
            $projectPath = getcwd();
            $envPath = $projectPath.'/.env';

            if (! file_exists($envPath)) {
                $this->warn('  ⚠ .env file is missing.');

                return false;
            }

            $envContent = file_get_contents($envPath);
            $criticalKeys = ['DB_PASSWORD', 'APP_KEY', 'DB_CONNECTION'];
            $issues = [];

            foreach ($criticalKeys as $key) {
                if (preg_match("/^#\s*{$key}=/m", $envContent)) {
                    $issues[] = "{$key} is commented out.";
                } elseif (! preg_match("/^{$key}=/m", $envContent)) {
                    $issues[] = "{$key} is missing entirely.";
                }
            }

            if (! empty($issues)) {
                $this->line('');
                foreach ($issues as $issue) {
                    $this->warn("  ⚠ {$issue}");
                }

                return false;
            }

            return true;
        });
    }

    protected function checkManifestIntegrity()
    {
        $environment = $this->option('environment');
        $path = ".infrastructure/k8s/overlays/{$environment}";

        $this->task("Checking Manifest Integrity ({$environment})", function () use ($path) {
            if (! is_dir(getcwd().'/'.$path)) {
                $this->warn("  ⚠ Manifest directory '{$path}' not found.");

                return false;
            }

            $output = [];
            $result = 0;
            exec("kubectl kustomize {$path} 2>&1", $output, $result);

            if ($result !== 0) {
                $this->line('');
                $this->error('  ✖ Malformed or broken Kubernetes configuration detected:');
                foreach (array_slice($output, 0, 5) as $line) {
                    $this->error("    {$line}");
                }

                return false;
            }

            return true;
        });
    }

    protected function checkHostsFile()
    {
        $this->task('Checking /etc/hosts resolution', function () {
            $projectPath = getcwd();
            $config = $this->getProjectConfig($projectPath);
            if (empty($config)) {
                return true;
            }

            $appName = basename($projectPath);
            $baseHost = "{$appName}.local";
            $required = [$baseHost, "vite.{$baseHost}"];

            foreach ($required as $host) {
                $ip = gethostbyname($host);
                if ($ip === $host || ($ip !== '127.0.0.1' && $ip !== '::1')) {
                    $this->line('');
                    $this->warn("  ⚠ Host '{$host}' does not resolve to 127.0.0.1.");
                    $this->info("    👉 FIX: Run 'larakube up' to update your hosts file.");

                    return false;
                }
            }

            return true;
        });
    }

    protected function checkPortConflicts()
    {
        $this->task('Checking Port Conflicts (80, 443, 5173)', function () {
            $ports = [80, 443, 5173];
            $conflicts = [];

            foreach ($ports as $port) {
                $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
                if (is_resource($connection)) {
                    fclose($connection);
                    // Port is occupied. We need to check if it's LaraKube (Traefik) or something else.
                    // This is hard to detect perfectly, so we just warn.
                    $conflicts[] = $port;
                }
            }

            if (! empty($conflicts)) {
                $this->line('');
                $this->warn('  ⚠ The following ports are already in use on your host: '.implode(', ', $conflicts));
                $this->info('    💡 If these are not being used by LaraKube/Traefik, they will cause connectivity issues.');

                return false;
            }

            return true;
        });
    }

    protected function checkClusterConnectivity()
    {
        $this->task('Checking Cluster Connectivity', function () {
            $output = shell_exec('kubectl cluster-info 2>&1');
            if (str_contains($output, 'Unable to connect') || str_contains($output, 'timeout')) {
                $this->line('');
                $this->error('  ✖ Cannot connect to Kubernetes. Is your Docker engine or OrbStack running?');

                return false;
            }

            return true;
        });
    }

    protected function checkPodHealth()
    {
        $environment = $this->option('environment');
        $namespace = $this->getNamespace($environment);

        $this->task("Checking Project Pods ({$namespace})", function () use ($namespace) {
            $output = shell_exec("kubectl get pods -n {$namespace} -o json 2>/dev/null");
            if (! $output) {
                $this->warn("  ⚠ No pods found in namespace '{$namespace}'. Is the app running?");

                return false;
            }

            $pods = json_decode($output, true)['items'] ?? [];
            $unhealthyCount = 0;

            foreach ($pods as $pod) {
                $name = $pod['metadata']['name'];
                $containerStatuses = $pod['status']['containerStatuses'] ?? [];

                foreach ($containerStatuses as $cs) {
                    $state = $cs['state']['waiting'] ?? null;
                    if ($state) {
                        $reason = $state['reason'];
                        $message = $state['message'] ?? '';

                        $this->line('');
                        $this->error("  ✖ Pod '{$name}' is unhealthy (Reason: {$reason})");

                        if ($reason === 'CreateContainerConfigError') {
                            $this->info('    💡 DIAGNOSIS: A required configuration key is missing from your Secret or ConfigMap.');
                            $this->info("    👉 FIX: Check your .env for commented-out variables and run 'larakube up' again.");
                        } elseif ($reason === 'CrashLoopBackOff' && str_contains($message, 'PANIC')) {
                            $this->info('    💡 DIAGNOSIS: Database volume corruption detected.');
                            $this->info("    👉 FIX: Run 'larakube reset' to clear corrupted data.");
                        } elseif ($reason === 'CrashLoopBackOff') {
                            $this->info('    💡 DIAGNOSIS: The application inside the pod is crashing on startup.');
                            $this->info("    👉 FIX: Run 'larakube logs {$name}' to see the application error.");
                        }

                        $unhealthyCount++;
                    }
                }
            }

            return $unhealthyCount === 0;
        });
    }
}
