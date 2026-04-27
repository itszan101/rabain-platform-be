<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $fillable = [
        'bag_id',
        'donor_id',
        'blood_type',
        'rhesus',
        'donation_date',
        'expired_date',
        'category_id',
        'status'
    ];

    public function donor()
    {
        return $this->belongsTo(Donor::class);
    }

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }
}