<?php

namespace Tests;

use Caronae\Models\Ride;
use Caronae\Models\RideUser;
use Caronae\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use DB;

class UserBanishTest extends TestCase
{
    use DatabaseTransactions;

    public function createRidesAsCaronista($user, $rideAttrs){
        factory(User::class, count($rideAttrs))->create()->each(function($u, $i) use($user, $rideAttrs) {
            $ride = factory(Ride::class)->make($rideAttrs[$i]);
            $ride->save();
            $u->rides()->save($ride, ['status' => 'driver']);
            $user->rides()->save($ride, ['status' => 'accepted']);
        });
    }

    public function createRideAsDriver($user, $rideAttr, $amount){
        $ride = factory(Ride::class)->create($rideAttr);
        $user->rides()->save($ride, ['status' => 'driver']);
        factory(User::class, $amount)->create()->each(function($user) use($ride) {
            $user->rides()->save($ride, ['status' => 'accepted']);
        });
    }

    public function testUserIsMarkedAsDeletedAt(){
        $user = factory(User::class)->create();

        $user->banish();

        $this->assertTrue($user->banned);
    }

    public function testNotDoneRequestsForRidesAreDeleted(){
        $user = factory(User::class)->create();

        $this->createRidesAsCaronista($user, [
            ['id' => 1, 'done' => true],
            ['id' => 2, 'done' => true],
            ['id' => 3, 'done' => false]
        ]);

        $this->createRideAsDriver($user, ['id' => 4, 'done' => true], 3);
        $this->createRideAsDriver($user, ['id' => 5, 'done' => true], 3);
        $this->createRideAsDriver($user, ['id' => 6, 'done' => false], 3);

        $this->assertTrue($user->rides()->count() == 6);

        $user->banish();

        $this->assertTrue($user->rides()->count() == 4);
        $this->assertTrue(RideUser::where('user_id', '=', $user->id)->where('ride_id', '=', 3)->count() == 0);
    }

    public function testRequestsForDrivesThatTheUserCreatedAreDeleted(){
        $user = factory(User::class)->create();

        $this->createRidesAsCaronista($user, [
            ['id' => 1, 'done' => true],
            ['id' => 2, 'done' => true],
            ['id' => 3, 'done' => false]
        ]);

        $this->createRideAsDriver($user, ['id' => 4, 'done' => true], 3);
        $this->createRideAsDriver($user, ['id' => 5, 'done' => true], 3);
        $this->createRideAsDriver($user, ['id' => 6, 'done' => false], 3);

        $this->assertTrue($user->rides()->count() == 6);

        $user->banish();

        $ride = Ride::find(6);
        $this->assertTrue($user->rides()->count() == 4);
        $this->assertNull($ride);
        $this->assertTrue(RideUser::where('ride_id', '=', 6)->count() == 0);
    }
}
