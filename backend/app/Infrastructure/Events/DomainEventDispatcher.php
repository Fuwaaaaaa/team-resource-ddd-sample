<?php

declare(strict_types=1);

namespace App\Infrastructure\Events;

use Illuminate\Contracts\Events\Dispatcher;

/**
 * Application 層の Handler から呼ばれる薄いラッパー。
 * Domain が生成した POPO イベントを Laravel の event() dispatcher に流す。
 *
 * Domain 層はこのクラスに依存しない（Laravel 非依存を維持）。
 */
final class DomainEventDispatcher
{
    public function __construct(private Dispatcher $events) {}

    /**
     * @param  iterable<object>  $events
     */
    public function dispatchAll(iterable $events): void
    {
        foreach ($events as $event) {
            $this->events->dispatch($event);
        }
    }
}
