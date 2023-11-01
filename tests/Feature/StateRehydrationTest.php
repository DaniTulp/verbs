<?php

use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbEvent;
use Illuminate\Support\Facades\Route;
use Thunk\Verbs\Examples\Bank\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Thunk\Verbs\Examples\Bank\States\AccountState;
use Thunk\Verbs\Examples\Bank\Events\AccountOpened;
use Thunk\Verbs\Examples\Bank\Events\MoneyDeposited;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create();
    
    Route::get('open', function () {
        $e = AccountOpened::fire();

        Verbs::commit();
        return $e->state(AccountState::class)->balance_in_cents;
    });

    Route::get('deposit', function () {
        $e = MoneyDeposited::fire(cents: 100);

        Verbs::commit();
        return $e->state(AccountState::class)->balance_in_cents;
    });

    dump('endBeforeEach');
});

// it('supports rehydrating a state from snapshots', function () {
//     $this->get('open')->assertSee(0);

//     expect(VerbSnapshot::query()->count())->toBe(1);
//     VerbEvent::truncate();

//     $this->get('deposit')->assertSee(100);
// });

it('supports rehydrating a state from events', function () {
    dump('start Test');

    $this->get('open')->assertSee(0);
    dump('request 1');

    // expect(VerbEvent::query()->count())->toBe(1);
    // // VerbSnapshot::truncate();
    // dump('request mid');

    // $this->get('deposit')->assertSee(100);
    // dump('request 2');

});

// it('supports rehydrating a state from a combination of snapshots and events', function () {
//     $this->get('open')->assertSee(0);

//     expect(VerbSnapshot::query()->count())->toBe(1);
//     VerbEvent::truncate();

//     $snapshot = VerbSnapshot::first();

//     $this->get('deposit')->assertSee(100);

//     expect(VerbEvent::query()->count())->toBe(1);
//     $snapshot->save();
    
//     $this->get('deposit')->assertSee(200);
// });
