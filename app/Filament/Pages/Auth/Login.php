<?php

namespace App\Filament\Auth;

use App\Models\User;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Checkbox;
use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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
                    ->label('Password')
                    ->password()
                    ->required(),

                Checkbox::make('remember')
                    ->label('Remember me'),
            ])
            ->statePath('data');
    }

    public function authenticate(): ?LoginResponseContract
    {
        $data     = $this->form->getState();
        $login    = $data['login'];
        $password = $data['password'];

        $payload = [
            'username' => $login,
            'password' => $password,
            'aplikasi' => 'simtam',
        ];

        Log::info('🔑 SSO Request Payload', $payload);

        $response = Http::withHeaders([
            'x-api-key' => env('SSO_API_KEY'),
            'Accept'    => 'application/json',
        ])->asForm()->post(env('SSO_API_BASE_URL') . '/auth/login', $payload);

        $json = $response->json() ?? [];

        Log::info('🔐 SSO Response', [
            'status' => $response->status(),
            'body'   => $json,
            'raw'    => $response->body(),
        ]);

        // ❌ Jika login gagal
        if ($response->failed() || ($json['success'] ?? false) !== true) {
            $apiMessage = $json['message'] ?? 'Login gagal. NIM/Email atau password salah.';
            $this->addError('data.login', $apiMessage);
            return null;
        }

        $userData = $json['user'] ?? null;

        if (! $userData || ! isset($userData['email'])) {
            $this->addError('data.login', 'Data user dari API tidak valid.');
            return null;
        }

        // Cek role langsung dari API
        if (($userData['role'] ?? null) !== 'superadmin-logbook') {
            $this->addError('data.login', 'Anda tidak memiliki akses ke halaman admin.');
            return null;
        }

        // Simpan user ke DB (tanpa api_token)
        $user = User::updateOrCreate(
            ['email' => $userData['email']],
            [
                'name'     => $userData['username'] ?? 'Guest',
                'username' => $userData['username'] ?? null,
                'role'     => $userData['role'] ?? 'mahasiswa',
                'password' => Hash::make($password),
            ]
        );

        Auth::login($user);
        session()->regenerate();

        return app(LoginResponseContract::class);
    }
}
