<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class AuthController extends Controller
{
    /**
     * Handle user registration.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'fcm_token' => 'nullable|string', // [PERUBAHAN] Menambahkan validasi untuk fcm_token
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 400);
        }

        $user = User::create([
            'name' => $request->email,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'fcm_token' => $request->fcm_token, // [PERUBAHAN] Menyimpan fcm_token saat registrasi
        ]);

        // [PERUBAHAN] Kirim notifikasi jika token ada
        if ($user->fcm_token) {
            $this->sendRegistrationNotification($user->fcm_token);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Registrasi Berhasil! Silakan masuk.'
        ], 201);
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

        // [PERUBAHAN] Jika berhasil login, buat dan kembalikan token API
        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login Berhasil!',
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

    /**
     * [FUNGSI BARU] Mengirim notifikasi selamat datang.
     */
    private function sendRegistrationNotification($fcmToken)
    {
        try {
            $messaging = app('firebase.messaging');
            $notification = Notification::create('Selamat Datang!', 'Registrasi Anda di aplikasi Rencanapa berhasil.');

            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification($notification);

            $messaging->send($message);
        } catch (\Exception $e) {
            // Jika gagal, catat error di log Laravel tapi jangan hentikan proses registrasi
            Log::error('Failed to send FCM notification: ' . $e->getMessage());
        }
    }

    /**
     * [FUNGSI BARU] Untuk update FCM token jika pengguna sudah login.
     */
    public function updateFcmToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'FCM token is required.'], 400);
        }

        $user = $request->user(); // Mengambil pengguna yang sedang login (via token)
        if ($user) {
            $user->fcm_token = $request->fcm_token;
            $user->save();
            return response()->json(['status' => 'success', 'message' => 'FCM token updated successfully.']);
        }

        return response()->json(['status' => 'error', 'message' => 'User not authenticated.'], 401);
    }
}
