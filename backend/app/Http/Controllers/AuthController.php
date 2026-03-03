<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controller untuk menangani siklus hidup otentikasi.
 * Mengelola pendaftaran, masuk, dan pengambilan data profil pengguna.
 */
class AuthController extends Controller
{
    /**
     * Mendaftarkan pengguna baru dan membuat dompet otomatis untuk Mitra.
     * Menggunakan Database Transaction untuk menjamin integritas data.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|in:customer,partner'
        ]);

        try {
            $user = DB::transaction(function () use ($request) {
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'role' => $request->role,
                ]);

                // Inisialisasi dompet jika pengguna mendaftar sebagai mitra
                if ($request->role === 'partner') {
                    Wallet::create([
                        'user_id' => $user->id,
                        'balance' => 0.00
                    ]);
                }

                return $user;
            });

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'data' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 201);

        } catch (\Exception $e) {
            Log::error('Registration Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat akun. Terjadi kesalahan pada peladen.'
            ], 500);
        }
    }

    /**
     * Memvalidasi kredensial dan mengembalikan token akses.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Kredensial tidak valid.'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => $user,
            'access_token' => $token,
        ], 200);
    }

    /**
     * Mengambil data pengguna aktif beserta informasi dompet.
     */
    public function user(Request $request)
    {
        // Memuat relasi wallet secara eager loading
        $user = $request->user()->load('wallet');

        return response()->json([
            'success' => true,
            'data' => $user
        ], 200);
    }
}
