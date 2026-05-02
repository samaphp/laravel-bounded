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
            ['Architecture validation', ['php', 'artisan', 'arch:validate', '--strict'], null],
            ['PHPStan', ['vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=2G'], null],
            ['Deptrac', ['vendor/bin/deptrac', 'analyse', '--no-progress'], null],
            // Force APP_ENV=testing for pest so phpunit.xml's testing env isn't shadowed
            // by APP_ENV=local inherited from the parent artisan process. PHPUnit's
            // <env> tags default to non-forcing; once APP_ENV is set in the parent
            // shell (which Laravel does on boot), they no longer take effect.
            ['Pest', ['vendor/bin/pest', '--coverage-clover=coverage.xml'], ['APP_ENV' => 'testing']],
        ];

        if (! $this->option('skip-coverage')) {
            $steps[] = ['Transaction coverage', ['php', 'artisan', 'arch:coverage:transactions'], null];
        }

        foreach ($steps as [$label, $command, $env]) {
            $this->newLine();
            $this->info("→ {$label}");

            $process = new Process($command, $basePath, $env);
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
