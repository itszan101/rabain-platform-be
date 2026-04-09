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
            'status' => 'lab'
        ]);

        return response()->json([
            'message' => 'Queue moved to lab'
        ]);
    }
}
