<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:create-admin
        {email? : The administrator\'s email address}
        {--name= : The administrator\'s name}
        {--password= : The password; one is generated if it is not given and you are not asked for it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an administrator account with a verified email address, e.g. on a new server';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $interactive = $this->input->isInteractive();

        $email = $this->argument('email') ?? ($interactive ? text('Email address', required: true) : null);
        $name = $this->option('name') ?? ($interactive ? text('Name', default: 'Administrator', required: true) : 'Administrator');
        $password = $this->option('password') ?? ($interactive ? password('Password (leave empty to generate one)') : '');
        $generated = $password === '';

        if ($generated) {
            $password = Str::password(20);
        }

        $validator = Validator::make(compact('email', 'name', 'password'), [
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => Role::Admin,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info("Administrator {$user->email} created.");

        if ($generated) {
            $this->line("Password: {$password}");
            $this->warn('Store this password safely; it is not shown again.');
        }

        return self::SUCCESS;
    }
}
