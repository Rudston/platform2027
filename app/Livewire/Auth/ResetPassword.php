<?php

namespace App\Livewire\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class ResetPassword extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->query('email', '');
    }

    public function resetPassword()
    {
        $this->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            [
                'email'                 => $this->email,
                'password'              => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token'                 => $this->token,
            ],
            function ($user) {
                // The model's 'hashed' cast hashes on save — do NOT Hash::make
                // here or the password would be double-hashed.
                $user->forceFill([
                    'password' => $this->password,
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            Session::flash('status', __($status));

            return redirect()->route('login');
        }

        $this->addError('email', __($status));
    }

    public function render()
    {
        return view('livewire.auth.reset-password')
            ->title(__('auth.reset.title'));
    }
}
