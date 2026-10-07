<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/** Used on the family server, where public sign-up is off. */
class CreateLandlord extends Command
{
    protected $signature = 'kuncia:create-landlord';

    protected $description = 'Create a landlord account (interactive)';

    public function handle(): int
    {
        $name = text('Full name', required: true);
        $email = text('Email', required: true, validate: fn (string $v) => Validator::make(['email' => $v], ['email' => 'email|unique:users,email'])->errors()->first('email') ?: null);
        $pass = password('Password (min 8)', required: true, validate: fn (string $v) => Validator::make(['p' => $v], ['p' => [Password::defaults()]])->errors()->first('p') ?: null);

        $user = User::create(['name' => $name, 'email' => $email, 'password' => $pass, 'role' => UserRole::Landlord]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info("Landlord {$user->email} created. Log in at ".config('app.url'));

        return self::SUCCESS;
    }
}
