<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendWelcomeNotification; // <-- [PERUBAHAN] Tambahkan ini

class AuthController extends Controller
{
    /**
     * Handle user registration.
     */
    public function register(Request $request)
    {
        Log::info('Menerima request registrasi:', $request->all());

        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6',
                'fcm_token' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first()
                ], 400);
            }

            $user = User::create([
                'name' => explode('@', $request->email)[0],
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'fcm_token' => $request->fcm_token,
            ]);

            // [PERUBAHAN] Kirim notifikasi menggunakan Job
            if (!empty($user->fcm_token)) {
                Log::info('Memasukkan SendWelcomeNotification ke dalam antrian untuk token: ' . $user->fcm_token);
                SendWelcomeNotification::dispatch($user->fcm_token);
            } else {
                Log::warning('Registrasi berhasil namun fcm_token tidak ada.');
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Registrasi Berhasil! Silakan masuk.'
            ], 201);

        } catch (\Exception $e) {
            Log::error('GAGAL SAAT REGISTRASI: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan pada server.'
            ], 500);
        }
    }

    /**
     * Handle user login.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 400);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email atau Password salah.'
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login Berhasil!',
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }
    
    // [PERUBAHAN] Fungsi sendRegistrationNotification sudah tidak diperlukan di sini
    // karena logikanya sudah dipindah ke dalam Job. Anda bisa menghapusnya.

    /**
     * Untuk update FCM token jika pengguna sudah login.
     */
    public function updateFcmToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'FCM token wajib diisi.'], 400);
        }

        $user = $request->user();
        if ($user) {
            $user->fcm_token = $request->fcm_token;
            $user->save();
            return response()->json(['status' => 'success', 'message' => 'FCM token berhasil diperbarui.']);
        }

        return response()->json(['status' => 'error', 'message' => 'User tidak terautentikasi.'], 401);
    }
}
