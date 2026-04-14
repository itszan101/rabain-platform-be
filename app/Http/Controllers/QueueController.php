<?php

namespace App\Http\Controllers;

use App\Models\Queue;

class QueueController extends Controller
{
    public function index()
    {
        return Queue::with('donor')
            ->get();
    }

    public function moveToLab($id)
    {
        $queue = Queue::findOrFail($id);

        $queue->update([
            'status' => 'Lab'
        ]);

        return response()->json([
            'message' => 'Queue moved to lab'
        ]);
    }
    public function moveToLabProcess($id)
    {
        $queue = Queue::findOrFail($id);

        $queue->update([
            'status' => 'Diproses'
        ]);

        return response()->json([
            'message' => 'Queue moved to Lab Process'
        ]);
    }
}
