<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Donor extends Model
{
    protected $fillable = [
        'nik',
        'name',
        'birth_date',
        'address',
        'gender',
        'citizenship',
        'blood_type_id',
        'rhesus_id',
        'phone'
    ];

    public function screening()
    {
        return $this->hasOne(Screening::class);
    }

    public function queue()
    {
        return $this->hasOne(Queue::class);
    }

    public function labResult()
    {
        return $this->hasOne(LabResult::class);
    }

    public function bloodType()
    {
        return $this->belongsTo(BloodType::class);
    }

    public function rhesus()
    {
        return $this->belongsTo(Rhesus::class);
    }
}
