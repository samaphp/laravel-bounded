<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Validators;

use JsonException;

/**
 * Forbids `autoload.files` in the consumer project's composer.json.
 *
 * `autoload.files` is the one escape hatch that the layer system can't
 * touch — anything declared there (typically `app/helpers.php`) is loaded
 * globally, reachable from anywhere, classified by no zone, and not subject
 * to any Deptrac/PHPStan rule we ship. It defeats the entire boundary
 * project. If shared logic is needed, put it in a class under a layer.
 */
final class AutoloadFilesValidator implements ValidatorInterface
{
    public function __construct(
        private readonly string $basePath,
    ) {
    }

    public function name(): string
    {
        return 'AutoloadFiles';
    }

    public function validate(): ValidatorResult
    {
        $composerPath = rtrim($this->basePath, '/') . '/composer.json';

        if (! is_file($composerPath)) {
            return new ValidatorResult(validator: $this->name());
        }

        $contents = (string) file_get_contents($composerPath);

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($contents, associative: true, depth: 64, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new ValidatorResult(validator: $this->name());
        }

        $problems = [];

        foreach (['autoload', 'autoload-dev'] as $section) {
            /** @var array<string, mixed>|mixed $autoload */
            $autoload = $decoded[$section] ?? null;
            if (! is_array($autoload)) {
                continue;
            }

            /** @var mixed $files */
            $files = $autoload['files'] ?? null;
            if (! is_array($files) || $files === []) {
                continue;
            }

            $problems[] = new Problem(
                kind: ProblemKind::AutoloadFilesPresent,
                context: "composer.json#/{$section}/files",
                message: sprintf(
                    '`%s.files` is not allowed. Files declared there are loaded globally, escape every Bounded layer, and are reachable from anywhere. Move shared logic into a class under a layer (Services, Integrations, etc.). Found: [%s].',
                    $section,
                    implode(', ', array_map(static fn ($f): string => (string) $f, $files)),
                ),
            );
        }

        return new ValidatorResult(
            validator: $this->name(),
            problems: $problems,
        );
    }
}
