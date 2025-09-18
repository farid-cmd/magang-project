<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function authenticate(Request $request)
    {
        // Validasi dasar (tanpa filter domain upr.ac.id)
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = $request->input('username');
        $password = $request->input('password');

        // Kirim ke API SSO
        $response = Http::withHeaders([
            'x-api-key' => env('SSO_API_KEY'),
            'Accept'    => 'application/json',
        ])->post(env('SSO_API_BASE_URL') . '/login', [
            'username' => $username,
            'password' => $password,
        ]);

        // Kalau gagal
        if ($response->failed()) {
            return back()->withErrors([
                'username' => 'Login gagal! (status: ' . $response->status() . ')',
                'debug'    => $response->body()
            ])->withInput();
        }

        $data = $response->json();

        // Simpan session user
        session([
            'sso_user' => $data['user'] ?? $data,
        ]);

        return redirect()->intended('/dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('sso_user');
        Auth::logout();
        return redirect('/login');
    }
}
