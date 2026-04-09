<?php

namespace App\Services;

use App\Models\Queue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class QueueService
{
    public static function generateQueueNumber(): string
    {
        return DB::transaction(function () {

            $last = Queue::whereDate('created_at', today())
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $number = 1;

            if ($last && preg_match('/^A(\d{3})$/', $last->queue_number, $matches)) {
                $number = (int) $matches[1] + 1;
            }

            return 'A' . str_pad($number, 3, '0', STR_PAD_LEFT);
        });
    }

    public static function generateBarcode(): string
    {
        return strtoupper(uniqid('BD'));
    }
}
