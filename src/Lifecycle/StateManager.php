<?php

namespace Thunk\Verbs\Lifecycle;

use Glhd\Bits\Bits;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use Symfony\Component\Uid\AbstractUid;
use Thunk\Verbs\Contracts\StoresEvents;
use Thunk\Verbs\Contracts\StoresSnapshots;
use Thunk\Verbs\Event;
use Thunk\Verbs\Exceptions\StateCacheSizeTooLow;
use Thunk\Verbs\Facades\Id;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\EventStateRegistry;
use Thunk\Verbs\Support\StateInstanceCache;
use UnexpectedValueException;

class StateManager
{
    protected bool $is_reconstituting = false;

    protected bool $is_replaying = false;

    public function __construct(
        protected Dispatcher $dispatcher,
        protected StoresSnapshots $snapshots,
        protected StoresEvents $events,
        protected StateInstanceCache $states,
    ) {
        $this->states->onDiscard(fn () => throw_unless($this->is_replaying, StateCacheSizeTooLow::class));
    }

    public function register(State $state): State
    {
        $state->id ??= snowflake_id();

        return $this->remember($state);
    }

    /** @param  class-string<State>  $type */
    public function load(Bits|UuidInterface|AbstractUid|int|string $id, string $type): State
    {
        $id = Id::from($id);
        $key = $this->key($id, $type);

        // FIXME: If the state we're loading has a last_event_id that's ahead of the registry's last_event_id, we need to re-build the state

        if ($state = $this->states->get($key)) {
            return $state;
        }

        if ($state = $this->snapshots->load($id, $type)) {
            if (! $state instanceof $type) {
                throw new UnexpectedValueException(sprintf('Expected State <%d> to be of type "%s" but got "%s"', $id, class_basename($type), class_basename($state)));
            }
        } else {
            // State::__construct() auto-registers the state with the StateManager, so we need to
            // skip the constructor until we've already set the ID.
            $reflect = new ReflectionClass($type);
            $state = $reflect->newInstanceWithoutConstructor();
            $state->id = $id;
            $state->__construct();
        }

        $this->remember($state);

        if (! $this->is_reconstituting) {
            $this->is_reconstituting = true;
            $this->reconstitute($state);
            $this->is_reconstituting = false;
        }

        return $state;
    }

    /** @param  class-string<State>  $type */
    public function singleton(string $type): State
    {
        // FIXME: If the state we're loading has a last_event_id that's ahead of the registry's last_event_id, we need to re-build the state

        if ($state = $this->states->get($type)) {
            return $state;
        }

        $state = $this->snapshots->loadSingleton($type) ?? $type::make();
        $state->id ??= snowflake_id();

        // We'll store a reference to it by the type for future singleton access
        $this->states->put($type, $state);
        $this->remember($state);

        $this->reconstitute($state, singleton: true);

        return $state;
    }

    public function writeSnapshots(): bool
    {
        return $this->snapshots->write($this->states->values());
    }

    public function setReplaying(bool $replaying): static
    {
        $this->is_replaying = $replaying;

        return $this;
    }

    public function reset(bool $include_storage = false): static
    {
        $this->states->reset();
        app(EventStateRegistry::class)->reset();

        $this->is_replaying = false;

        if ($include_storage) {
            $this->snapshots->reset();
        }

        return $this;
    }

    public function prune(): static
    {
        $this->states->prune();

        return $this;
    }

    protected function reconstitute(State $state, bool $singleton = false): static
    {
        // When we're replaying, the Broker is in charge of applying the correct events
        // to the State, so we only need to do it *outside* of replays.
        if (! $this->is_replaying) {
            //            $this->events
            //                ->read(state: $state, after_id: $state->last_event_id, singleton: $singleton)
            //                ->each(fn (Event $event) => $this->dispatcher->apply($event));
            (new StateReconstructor($this->events, $this->dispatcher))
                ->reconstruct($state::class, $singleton ? null : $state->id);
        }

        return $this;
    }

    protected function remember(State $state): State
    {
        $key = $this->key($state->id, $state::class);

        $this->states->put($key, $state);

        return $state;
    }

    protected function key(string|int $id, string $type): string
    {
        return "{$type}:{$id}";
    }
}
