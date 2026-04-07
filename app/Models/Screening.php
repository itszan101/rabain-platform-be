<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Screening extends Model
{
    protected $fillable = [
        'donor_id',
        'is_healthy',
        'is_taking_medicine',
        'last_donation_date'
    ];
}
