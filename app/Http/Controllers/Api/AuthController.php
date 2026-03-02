<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\Registered;

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
                'message' => 'Email belum diverifikasi.',
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

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }
}
