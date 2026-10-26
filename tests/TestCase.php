<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\Attributes\Seeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

#[Seeder(RolesAndPermissionsSeeder::class)]
abstract class TestCase extends BaseTestCase
{
    //
}
