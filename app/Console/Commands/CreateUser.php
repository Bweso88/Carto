<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

#[Signature('merimap:user
    {email : Adresse e-mail (identifiant de connexion)}
    {--name= : Nom affiché}
    {--role=admin : admin, editor ou reader}
    {--password= : Mot de passe (demandé interactivement si absent)}')]
#[Description('Crée un compte MeriMap ou met à jour son rôle et son mot de passe')]
class CreateUser extends Command
{
    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $user = User::firstWhere('email', $email);
        $password = $this->option('password') ?? $this->secret($user ? 'Nouveau mot de passe (laisser vide pour ne pas changer)' : 'Mot de passe');

        $validator = Validator::make(
            ['email' => $email, 'role' => $this->option('role'), 'password' => $password],
            [
                'email' => ['required', 'email'],
                'role' => ['required', Rule::enum(UserRole::class)],
                'password' => [$user ? 'nullable' : 'required', Password::min(10)],
            ],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user ??= new User(['email' => $email]);
        $user->name = $this->option('name') ?? $user->name ?? strstr($email, '@', true);
        $user->role = UserRole::from($this->option('role'));
        if (filled($password)) {
            $user->password = $password;
        }
        // Compte créé par un administrateur : pas de vérification d'e-mail nécessaire
        $user->email_verified_at ??= now();
        $created = ! $user->exists;
        $user->save();

        $this->info(($created ? 'Compte créé' : 'Compte mis à jour')." : {$user->email} ({$user->role->label()})");

        return self::SUCCESS;
    }
}
