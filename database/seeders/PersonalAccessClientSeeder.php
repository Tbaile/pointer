<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

class PersonalAccessClientSeeder extends Seeder
{
    /**
     * Create the client that issues the agents' tokens, safe to run on every start.
     */
    public function run(ClientRepository $clients): void
    {
        $exists = Passport::client()->newQuery()
            ->where('revoked', false)
            ->get()
            ->contains(fn (Client $client): bool => $client->hasGrantType('personal_access'));

        if (! $exists) {
            $clients->createPersonalAccessGrantClient('Pointer agents', 'users');
        }
    }
}
