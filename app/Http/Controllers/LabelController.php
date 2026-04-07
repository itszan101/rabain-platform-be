<?php

namespace App\Http\Controllers;

use App\Models\Donor;

class LabelController extends Controller
{
    public function sample($id)
    {
        $donor = Donor::with('queue')->findOrFail($id);

        return response()->json([
            'type' => 'sample',
            'data' => $donor
        ]);
    }

    public function blood($id)
    {
        $donor = Donor::with(['queue', 'labResult'])->findOrFail($id);

        if (!$donor->labResult || !$donor->labResult->is_eligible) {
            return response()->json(['message' => 'Belum layak'], 422);
        }

        return response()->json([
            'type' => 'blood',
            'data' => $donor
        ]);
    }
}
