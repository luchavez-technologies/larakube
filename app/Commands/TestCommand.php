<?php

namespace App\Commands;

use App\Traits\InteractsWithEnvironments;
use App\Traits\LaraKubeOutput;
use LaravelZero\Framework\Commands\Command;

class TestCommand extends Command
{
    use InteractsWithEnvironments, LaraKubeOutput;

    protected $signature = 'test {environment=local : The environment to test}';

    protected $description = 'Perform a smoke test to ensure the application is reachable';

    public function handle(): int
    {
        $this->renderHeader();

        $projectPath = getcwd();
        $ingressPath = $projectPath.'/.infrastructure/k8s/base/ingress.yaml';

        if (! file_exists($ingressPath)) {
            $this->laraKubeError('Ingress configuration not found.');

            return 1;
        }

        // Get the host from ingress
        $content = file_get_contents($ingressPath);
        if (preg_match('/host: (.*)/', $content, $matches)) {
            $host = trim($matches[1]);
            $protocols = ['https', 'http'];
            $maxAttempts = 5;
            $success = false;

            foreach ($protocols as $protocol) {
                $url = "{$protocol}://{$host}";
                $this->laraKubeInfo("Testing connectivity to {$url}...");

                for ($i = 1; $i <= $maxAttempts; $i++) {
                    // Use -k to allow self-signed certificates
                    // Use -s to be silent, -o /dev/null to discard output, -w to get status code
                    $httpCode = trim(shell_exec("curl -k -s -o /dev/null -w \"%{http_code}\" {$url}") ?? '');

                    if ($httpCode === '200') {
                        $this->laraKubeInfo("SUCCESS! Your application is reachable via {$protocol} (HTTP 200).");
                        $success = true;
                        break 2;
                    }

                    if ($i < $maxAttempts) {
                        sleep(2);
                    }
                }
            }

            if (! $success) {
                $this->laraKubeError('FAILED. Could not reach your application after several attempts.');
                $this->line("Check your /etc/hosts and ensure your pods are running with 'larakube dashboard'.");

                return 1;
            }
        }

        return 0;
    }
}
