<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Queue extends Model
{
    protected $fillable = [
        'donor_id',
        'queue_number',
        'barcode',
        'status'
    ];

    public function donor()
    {
        return $this->belongsTo(Donor::class);
    }
}
