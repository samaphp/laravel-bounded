<?php

declare(strict_types=1);

namespace Samaphp\LaravelBounded\Validators;

enum ProblemKind: string
{
    case ZoneOverlap = 'zone_overlap';
    case ScanPathMissing = 'scan_path_missing';
    case ScanPathEmpty = 'scan_path_empty';
    case AutoloadFilesPresent = 'autoload_files_present';
}
