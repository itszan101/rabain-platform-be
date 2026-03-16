<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'birth_date',
        'gender',
        'email',
        'password',
        'avatar',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'birth_date' => 'date',
    ];

    public function getProfilePictureUrlAttribute()
    {
        if ($this->profile_picture && Storage::disk('public')->exists($this->profile_picture)) {
            return asset('storage/' . $this->profile_picture);
        }
        return asset('images/default-avatar.png'); // fallback default
    }
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\VerifyEmailNotification);
    }
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }
}
