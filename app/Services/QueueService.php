<?php

namespace App\Services;

use App\Models\Queue;
use Carbon\Carbon;

class QueueService
{
    public static function generateQueueNumber(): string
    {
        $date = Carbon::now()->format('dmY');

        $last = Queue::whereDate('created_at', now())
            ->latest()
            ->first();

        $number = $last ? ((int) substr($last->queue_number, -4)) + 1 : 1;

        return 'Q-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    public static function generateBarcode(): string
    {
        return strtoupper(uniqid('BD'));
    }
}