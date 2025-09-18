<?php

namespace App\Filament\Auth;

use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Checkbox;
use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Exceptions\ValidationException; // custom exception
use App\Models\User;

class Login extends BaseLogin
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('login')
                    ->label('NIM / Email')
                    ->required()
                    ->autofocus(),

                TextInput::make('password')
                    ->label(__('Password'))
                    ->password()
                    ->required(),

                Checkbox::make('remember')
                    ->label(__('Remember me')),
            ])
            ->statePath('data');
    }

    public function authenticate(): LoginResponseContract
    {
        $data     = $this->form->getState();
        $login    = $data['login'];
        $password = $data['password'];

        // 🔎 Validasi email hanya boleh UPR atau Gmail
        if (str_contains($login, '@') &&
            ! str_ends_with($login, '@upr.ac.id') &&
            ! str_ends_with($login, '@gmail.com')) {
            throw ValidationException::withMessages([
                'data.login' => 'Hanya email UPR atau Gmail yang bisa login.',
            ]);
        }

        $payload = [
            'username' => $login, // API Siuber hanya terima `username`
            'password' => $password,
            'aplikasi' => 'simtam',
        ];

        // 🔎 Log payload untuk debug
        Log::info('🔑 SSO Request Payload', $payload);

        $response = Http::withHeaders([
            'x-api-key' => env('SSO_API_KEY'),
            'Accept'    => 'application/json',
        ])->asForm()->post(env('SSO_API_BASE_URL') . '/auth/login', $payload);

        Log::info('🔐 SSO Response', [
            'status' => $response->status(),
            'body'   => $response->json(),
            'raw'    => $response->body(),
        ]);

        if ($response->failed() || $response->json('success') !== true) {
            $apiMessage = $response->json('message') ?? 'Login gagal. NIM/Email atau password salah.';
            throw ValidationException::withMessages([
                'data.login' => $apiMessage,
            ]);
        }

        $result   = $response->json();
        $userData = $result['user'] ?? null;

        if (! $userData || ! isset($userData['email'])) {
            throw ValidationException::withMessages([
                'data.login' => 'Data user dari API tidak valid.',
            ]);
        }

        $user = User::updateOrCreate(
            ['email' => $userData['email']],
            [
                'name'      => $userData['username'] ?? 'Guest',
                'username'  => $userData['username'] ?? null,
                'role'      => $userData['role'] ?? 'mahasiswa',
                'api_token' => $result['token'] ?? null,
                'password'  => bcrypt($password),
            ]
        );

        Auth::login($user);
        session()->regenerate();

        return app(LoginResponseContract::class);
    }
}
