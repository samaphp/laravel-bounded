<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\PHPStan\Helpers;

final class ZoneClassifier
{
    /**
     * Default zone definitions used by PHPStan rules.
     *
     * **MUST mirror `config/bounded.php`'s `zones` block.** PHPStan runs
     * without booting Laravel, so it can't read the consumer's config at
     * analysis time. The duplication is mechanically necessary — change
     * one, change the other, or rules drift silently from validators.
     *
     * Consumers with custom zones can configure ZoneClassifier via PHPStan
     * service definition (autowired by extension.neon).
     */
    public const DEFAULT_ZONES = [
        'logic' => [
            'app/Http/Controllers',
            'app/Services',
            'app/Integrations',
            'app/Console/Commands',
            'app/Jobs',
        ],
        'framework_bridge' => [
            'app/Providers',
            'app/Http/Middleware',
        ],
        'repository' => [
            'app/Repositories',
            'app/Queries',
        ],
    ];

    /**
     * @param  array<string, list<string>>|null  $zones  Custom zones. Falls back to DEFAULT_ZONES when null.
     */
    public function __construct(
        private readonly ?array $zones = null,
    ) {
    }

    /**
     * Classify a file path into a zone, or null if outside any known zone.
     */
    public function zoneFor(string $absolutePath): ?string
    {
        $normalized = str_replace('\\', '/', $absolutePath);

        foreach ($this->effectiveZones() as $zoneName => $paths) {
            foreach ($paths as $zonePath) {
                $needle = '/' . trim($zonePath, '/') . '/';
                if (str_contains($normalized, $needle)) {
                    return $zoneName;
                }
            }
        }

        return null;
    }

    public function isInZone(string $absolutePath, string $zoneName): bool
    {
        return $this->zoneFor($absolutePath) === $zoneName;
    }

    /**
     * @param  list<string>  $zoneNames
     */
    public function isInAnyZone(string $absolutePath, array $zoneNames): bool
    {
        $zone = $this->zoneFor($absolutePath);

        return $zone !== null && in_array($zone, $zoneNames, true);
    }

    /**
     * Match the file's path against a specific sub-zone path, e.g.
     * `isUnderPath($file, 'app/Services')`. Useful for rules that
     * scope to a single directory rather than a whole zone.
     */
    public function isUnderPath(string $absolutePath, string $relativePath): bool
    {
        $normalized = str_replace('\\', '/', $absolutePath);
        $needle = '/' . trim($relativePath, '/') . '/';

        return str_contains($normalized, $needle);
    }

    /**
     * @return array<string, list<string>>
     */
    private function effectiveZones(): array
    {
        return $this->zones ?? self::DEFAULT_ZONES;
    }
}
