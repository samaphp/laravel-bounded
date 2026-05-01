<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Samaphp\LaravelBounded\Validators\NoListenersValidator;

beforeEach(function () {
    $this->fixturePath = sys_get_temp_dir() . '/laravel-bounded-nl-' . uniqid();
    mkdir($this->fixturePath);

    $this->writeFile = function (string $relativePath, string $contents): void {
        $fullPath = $this->fixturePath . '/' . ltrim($relativePath, '/');
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, recursive: true);
        }
        file_put_contents($fullPath, $contents);
    };
});

afterEach(function () {
    if (isset($this->fixturePath) && is_dir($this->fixturePath)) {
        File::deleteDirectory($this->fixturePath);
    }
});

it('passes when app/Listeners and app/Observers do not exist', function () {
    $result = (new NoListenersValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('passes when app/Listeners and app/Observers are empty directories', function () {
    mkdir($this->fixturePath . '/app/Listeners', recursive: true);
    mkdir($this->fixturePath . '/app/Observers', recursive: true);

    $result = (new NoListenersValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeTrue();
});

it('emits a Violation for each file in app/Listeners', function () {
    ($this->writeFile)('app/Listeners/SendEmail.php', '<?php class SendEmail {}');
    ($this->writeFile)('app/Listeners/AnotherListener.php', '<?php class AnotherListener {}');

    $result = (new NoListenersValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    expect($result->violations())->toHaveCount(2);

    $files = array_map(static fn ($v) => $v->file, $result->violations());
    expect($files)
        ->toContain('app/Listeners/SendEmail.php')
        ->toContain('app/Listeners/AnotherListener.php');
});

it('emits a Violation for each file in app/Observers', function () {
    ($this->writeFile)('app/Observers/UserObserver.php', '<?php class UserObserver {}');

    $result = (new NoListenersValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    expect($result->violations())->toHaveCount(1);
    expect($result->violations()[0]->file)->toBe('app/Observers/UserObserver.php');
});

it('emits a Violation for classes implementing EventSubscriberInterface anywhere under app/', function () {
    ($this->writeFile)('app/Services/MySubscriber.php', <<<'PHP'
        <?php
        namespace App\Services;
        use Symfony\Component\EventDispatcher\EventSubscriberInterface;
        final class MySubscriber implements EventSubscriberInterface
        {
            public static function getSubscribedEvents(): array { return []; }
        }
        PHP);

    $result = (new NoListenersValidator($this->fixturePath))->validate();

    expect($result->passed())->toBeFalse();
    expect($result->violations())->toHaveCount(1);
    expect($result->violations()[0]->message)->toContain('EventSubscriberInterface');
    expect($result->violations()[0]->file)->toBe('app/Services/MySubscriber.php');
});

it('does not double-violate when a listener file also implements EventSubscriberInterface', function () {
    ($this->writeFile)('app/Listeners/SubscriberListener.php', <<<'PHP'
        <?php
        namespace App\Listeners;
        use Symfony\Component\EventDispatcher\EventSubscriberInterface;
        final class SubscriberListener implements EventSubscriberInterface
        {
            public static function getSubscribedEvents(): array { return []; }
        }
        PHP);

    $result = (new NoListenersValidator($this->fixturePath))->validate();

    expect($result->violations())->toHaveCount(1);
});

it('skips app/Listeners when listed in ignoredScanPaths', function () {
    ($this->writeFile)('app/Listeners/Allowed.php', '<?php class Allowed {}');

    $result = (new NoListenersValidator(
        $this->fixturePath,
        ignoredScanPaths: ['app/Listeners'],
    ))->validate();

    expect($result->passed())->toBeTrue();
});

it('still flags app/Observers when only app/Listeners is ignored', function () {
    ($this->writeFile)('app/Listeners/Allowed.php', '<?php class Allowed {}');
    ($this->writeFile)('app/Observers/StillForbidden.php', '<?php class StillForbidden {}');

    $result = (new NoListenersValidator(
        $this->fixturePath,
        ignoredScanPaths: ['app/Listeners'],
    ))->validate();

    expect($result->violations())->toHaveCount(1);
    expect($result->violations()[0]->file)->toBe('app/Observers/StillForbidden.php');
});

it('exposes its name on both the validator and the result', function () {
    $validator = new NoListenersValidator($this->fixturePath);

    expect($validator->name())->toBe('NoListeners');
    expect($validator->validate()->validator)->toBe('NoListeners');
});
