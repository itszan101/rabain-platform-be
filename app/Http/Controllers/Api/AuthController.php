<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email'      => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->whereNull('deleted_at'),
            ],
            'birth_date' => ['nullable', 'date'],
            'gender'     => ['nullable', 'in:male,female'],
            'password'   => ['required', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => $data['email'],
            'birth_date' => $data['birth_date'] ?? null,
            'gender'     => $data['gender'] ?? null,
            'password'   => Hash::make($data['password']),
        ]);

        $user->assignRole('user');

        event(new Registered($user)); // Kirim email verification

        return response()->json([
            'message' => 'Registrasi berhasil. Silakan cek email untuk verifikasi.',
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Akun belum diverifikasi, Silahkan cek email untuk verifikasi.',
            ], 403);
        }

        // Optional: revoke all old tokens
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        // Ambil data role dan permission dari Spatie Permission
        $roles = $user->getRoleNames(); // ex: ['super-admin']
        $permissions = $user->getAllPermissions()->pluck('name'); // ex: ['view users', 'edit users', ...]

        return response()->json([
            'token'       => $token,
            'roles'       => $roles,
            'permissions' => $permissions,
            'user'        => [
                'id'    => $user->id,
                'name'  => $user->last_name,
                'email' => $user->email,
            ],
        ]);
    }

    public function emailVerify(Request $request)
    {
        if (! URL::hasValidSignature($request)) {
            return response()->json(['message' => 'Invalid or expired link'], 403);
        }

        $user = User::findOrFail($request->id);

        if (! hash_equals(
            sha1($user->getEmailForVerification()),
            $request->hash
        )) {
            return response()->json(['message' => 'Invalid hash'], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        // Hapus token lama (opsional tapi disarankan)
        $user->tokens()->delete();

        // Buat personal access token
        $token = $user->createToken('email-verified-login')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    public function resendEmailVerification(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified'
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification email resent successfully'
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Reset link sent successfully',
            ]);
        }

        return response()->json([
            'message' => 'Unable to send reset link'
        ], 400);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => bcrypt($request->password)
                ])->save();

                // Hapus semua token lama
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password reset successfully'
            ]);
        }

        return response()->json([
            'message' => 'Invalid or expired token'
        ], 400);
    }

    public function googleLogin(Request $request)
    {

        $request->validate([
            'id_token' => 'required'
        ]);

        /*
    |---------------------------------------------------
    | Verifikasi token Google
    |---------------------------------------------------
    */

        $response = Http::get(
            'https://oauth2.googleapis.com/tokeninfo',
            [
                'id_token' => $request->id_token
            ]
        );

        if (!$response->ok()) {

            return response()->json([
                'message' => 'Invalid Google token'
            ], 401);
        }

        $google = $response->json();

        $provider = 'google';
        $providerId = $google['sub'];

        /*
    |---------------------------------------------------
    | Ambil nama
    |---------------------------------------------------
    */

        $firstName = $google['given_name'] ?? null;
        $lastName  = $google['family_name'] ?? null;

        /*
    |---------------------------------------------------
    | Fallback jika Google tidak memberi given/family
    |---------------------------------------------------
    */

        if (!$firstName) {

            $name = explode(' ', $google['name'] ?? 'Google User');

            $firstName = $name[0] ?? 'Google';
            $lastName  = $name[1] ?? null;
        }

        /*
    |---------------------------------------------------
    | Cari social account
    |---------------------------------------------------
    */

        $social = SocialAccount::where([
            'provider' => $provider,
            'provider_id' => $providerId
        ])->first();

        if ($social) {

            $user = $social->user;
        } else {

            /*
        |---------------------------------------------------
        | Cek apakah email sudah ada
        |---------------------------------------------------
        */

            $user = User::where('email', $google['email'])->first();

            if (!$user) {

                $user = User::create([
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'email' => $google['email'],
                    'avatar' => $google['picture'] ?? null,
                    'password' => bcrypt(Str::random(32)),
                    'email_verified_at' => now()
                ]);
            }

            /*
        |---------------------------------------------------
        | Simpan relasi social account
        |---------------------------------------------------
        */

            SocialAccount::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_id' => $providerId
            ]);
        }

        /*
    |---------------------------------------------------
    | Update avatar jika berubah
    |---------------------------------------------------
    */

        if (!empty($google['picture'])) {

            $user->update([
                'avatar' => $google['picture']
            ]);
        }

        /*
    |---------------------------------------------------
    | Generate Sanctum Token
    |---------------------------------------------------
    */

        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }
}
