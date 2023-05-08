<?php

namespace Thunk\Verbs;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Date;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Thunk\Verbs\Lifecycle\Broker;
use Thunk\Verbs\Lifecycle\Bus;
use Thunk\Verbs\Lifecycle\Repositories\ContextRepository;
use Thunk\Verbs\Lifecycle\Repositories\EventRepository;
use Thunk\Verbs\Snowflakes\Bits;
use Thunk\Verbs\Snowflakes\Factory;
use Thunk\Verbs\Snowflakes\SequenceResolver;

class VerbsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('verbs')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_verb_events_table');
    }

    public function packageRegistered()
    {
        $this->app->singleton(Bus::class);
        $this->app->alias(Bus::class, Contracts\Bus::class);

        $this->app->singleton(EventRepository::class);
        $this->app->alias(EventRepository::class, Contracts\EventRepository::class);

        $this->app->singleton(ContextRepository::class);
        $this->app->alias(ContextRepository::class, Contracts\ContextRepository::class);

        $this->app->singleton(Broker::class);
        $this->app->alias(Broker::class, Contracts\Broker::class);

        $this->app->singleton(Bits::class);

        $this->app->singleton(SequenceResolver::class);
        $this->app->alias(SequenceResolver::class, Contracts\SequenceResolver::class);

        $this->app->singleton(Factory::class, function (Container $container) {
            return new Factory(
                epoch: Date::parse(config('verbs.snowflake_start_date')),
                datacenter_id: (int) (config('verbs.snowflake_datacenter_id') ?? random_int(0, 31)),
                worker_id: (int) (config('verbs.snowflake_worker_id') ?? random_int(0, 31)),
                precision: 3,
                sequence: $container->make(Contracts\SequenceResolver::class),
                bits: $container->make(Bits::class),
            );
        });
    }
}
