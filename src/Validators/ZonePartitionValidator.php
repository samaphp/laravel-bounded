<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Validators;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class ZonePartitionValidator implements ValidatorInterface
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {
    }

    public function name(): string
    {
        return 'ZonePartition';
    }

    public function validate(): ValidatorResult
    {
        /** @var array<string, list<string>> $zones */
        $zones = $this->config->get('bounded.zones', []);

        $pathOccurrences = [];
        foreach ($zones as $zoneName => $paths) {
            foreach ($paths as $path) {
                $pathOccurrences[$path][] = $zoneName;
            }
        }

        $problems = [];
        foreach ($pathOccurrences as $path => $zoneNames) {
            if (count($zoneNames) > 1) {
                $problems[] = new Problem(
                    kind: ProblemKind::ZoneOverlap,
                    context: 'config/bounded.php',
                    message: sprintf(
                        'Path [%s] is in multiple zones: [%s]. Zones must partition — each path appears in exactly one zone.',
                        $path,
                        implode(', ', $zoneNames),
                    ),
                );
            }
        }

        return new ValidatorResult(
            validator: $this->name(),
            problems: $problems,
        );
    }
}
