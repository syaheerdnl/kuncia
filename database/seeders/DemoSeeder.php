<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\SampleData;
use Illuminate\Database\Seeder;

/**
 * Local development data. All accounts use the password "password".
 * Never run on the family server (it holds real data).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $landlord = User::create([
            'name' => 'Encik Hakim (Landlord)',
            'email' => 'landlord@kuncia.test',
            'password' => 'password',
            'role' => UserRole::Landlord,
        ]);
        $landlord->forceFill(['email_verified_at' => now()])->save();

        SampleData::seed($landlord, ['tenant' => 'tenant@kuncia.test', 'tech' => 'tech@kuncia.test'], 'password');
    }
}
