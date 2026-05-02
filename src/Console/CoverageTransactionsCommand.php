<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Console;

use Illuminate\Console\Command;
use Samaphp\LaravelBounded\Coverage\TransactionCoverageGate;

final class CoverageTransactionsCommand extends Command
{
    protected $signature = 'arch:coverage:transactions {--report=coverage.xml : Path to Clover coverage report (relative to project root)}';

    protected $description = 'Assert non-zero coverage on every line containing Transaction::run.';

    public function handle(): int
    {
        $gate = new TransactionCoverageGate();
        $result = $gate->check(
            $this->laravel->basePath('app'),
            $this->laravel->basePath((string) $this->option('report')),
        );

        if ($result->error !== null) {
            $this->error($result->error);

            return self::FAILURE;
        }

        if ($result->totalCallSites === 0) {
            $this->info('No Transaction::run call sites found.');

            return self::SUCCESS;
        }

        if ($result->passed) {
            $this->info(sprintf(
                'All %d Transaction::run call sites covered.',
                $result->totalCallSites,
            ));

            return self::SUCCESS;
        }

        $this->error(sprintf(
            '%d/%d Transaction::run call sites uncovered:',
            count($result->uncoveredCallSites),
            $result->totalCallSites,
        ));
        foreach ($result->uncoveredCallSites as [$file, $line]) {
            $relative = str_replace($this->laravel->basePath() . '/', '', $file);
            $this->line(sprintf('  - %s:%d', $relative, $line));
        }

        return self::FAILURE;
    }
}
