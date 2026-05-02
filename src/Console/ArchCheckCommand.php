<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Console;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

final class ArchCheckCommand extends Command
{
    protected $signature = 'arch:check {--skip-coverage : Run the chain without the transaction-coverage gate}';

    protected $description = 'Run the full bounded check chain: arch:validate --strict, phpstan, deptrac, pest, transaction coverage.';

    public function handle(): int
    {
        $basePath = $this->laravel->basePath();

        $steps = [
            ['Architecture validation', ['php', 'artisan', 'arch:validate', '--strict']],
            ['PHPStan', ['vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=2G']],
            ['Deptrac', ['vendor/bin/deptrac', 'analyse', '--no-progress']],
            ['Pest', ['vendor/bin/pest', '--coverage-clover=coverage.xml']],
        ];

        if (! $this->option('skip-coverage')) {
            $steps[] = ['Transaction coverage', ['php', 'artisan', 'arch:coverage:transactions']];
        }

        foreach ($steps as [$label, $command]) {
            $this->newLine();
            $this->info("→ {$label}");

            $process = new Process($command, $basePath);
            $process->setTimeout(null);
            $process->run(function ($_type, string $buffer): void {
                $this->output->write($buffer);
            });

            if (! $process->isSuccessful()) {
                $this->newLine();
                $this->error("✗ {$label} failed — chain stopped.");

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('All checks passed.');

        return self::SUCCESS;
    }
}
