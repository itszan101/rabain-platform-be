<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabResult extends Model
{
    protected $fillable = [
        'donor_id',
        'systolic',
        'diastolic',
        'hemoglobin',
        'weight',
        'temperature',
        'hiv',
        'hcv',
        'hbsag',
        'sifilis',
        'notes',
        'is_eligible',
        'is_imltd'
    ];

    public function donor()
    {
        return $this->belongsTo(Donor::class);
    }
}
