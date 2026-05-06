<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Console;

use Illuminate\Console\Command;
use Samaphp\LaravelBounded\BoundedServiceProvider;
use Samaphp\LaravelBounded\Validators\SupportsStrictMode;
use Samaphp\LaravelBounded\Validators\ValidatorInterface;
use Samaphp\LaravelBounded\Validators\ValidatorResult;

final class ArchValidateCommand extends Command
{
    protected $signature = 'arch:validate {--strict : Bypass ignore lists for file-level violations}';

    protected $description = 'Run all Bounded validators against the application code.';

    public function handle(): int
    {
        /** @var iterable<ValidatorInterface> $validators */
        $validators = $this->laravel->tagged(BoundedServiceProvider::VALIDATOR_TAG);
        $validators = iterator_to_array($validators);

        if ($this->option('strict')) {
            foreach ($validators as $validator) {
                if ($validator instanceof SupportsStrictMode) {
                    $validator->setStrict(true);
                }
            }
        }

        $totalViolations = 0;
        $totalProblems = 0;
        $failedValidators = 0;

        foreach ($validators as $validator) {
            $result = $validator->validate();
            $this->renderResult($result);

            if (! $result->passed()) {
                $failedValidators++;
                $totalViolations += count($result->violations());
                $totalProblems += count($result->problems());
            }
        }

        $this->newLine();
        $this->line(sprintf(
            'Validators: %d total, %d failed. Violations: %d. Problems: %d.',
            count($validators),
            $failedValidators,
            $totalViolations,
            $totalProblems,
        ));

        return $failedValidators === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function renderResult(ValidatorResult $result): void
    {
        if ($result->passed()) {
            $this->line(sprintf('  <fg=green>OK</> %s', $result->validator));

            return;
        }

        $this->line(sprintf(
            '  <fg=red>FAIL</> %s — %d violation(s), %d problem(s)',
            $result->validator,
            count($result->violations()),
            count($result->problems()),
        ));

        foreach ($result->violations() as $violation) {
            $location = $violation->line !== null
                ? "{$violation->file}:{$violation->line}"
                : $violation->file;
            $this->line("      <fg=yellow>{$location}</> — {$violation->message}");
        }

        foreach ($result->problems() as $problem) {
            $this->line(sprintf(
                '      <fg=cyan>[%s]</> %s — %s',
                $problem->kind->value,
                $problem->context,
                $problem->message,
            ));
        }
    }
}
