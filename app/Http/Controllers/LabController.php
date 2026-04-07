<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Donor;
use App\Models\LabResult;
use Illuminate\Support\Facades\DB;
use App\Models\Queue;

class LabController extends Controller
{
    public function list()
    {
        return Queue::with('donor')
            ->where('status', 'lab')
            ->get();
    }

    public function process(Request $request)
    {
        $request->validate([
            'donor_id' => 'required|exists:donors,id',

            'systolic' => 'required|integer',
            'diastolic' => 'required|integer',
            'hemoglobin' => 'required|numeric',
            'weight' => 'required|numeric',
            'temperature' => 'required|numeric',

            'hiv' => 'required|in:non-reactive,reactive',
            'hcv' => 'required|in:non-reactive,reactive',
            'hbsag' => 'required|in:non-reactive,reactive',
            'sifilis' => 'required|in:non-reactive,reactive',

            'notes' => 'nullable|string'
        ]);

        $donor = Donor::with('queue')->findOrFail($request->donor_id);

        // ❗ HARD GUARD
        if ($donor->is_blacklisted) {
            return response()->json(['message' => 'Donor sudah blacklist'], 422);
        }

        if (!$donor->queue || $donor->queue->status !== 'lab') {
            return response()->json([
                'message' => 'Donor belum masuk tahap lab'
            ], 422);
        }

        if (LabResult::where('donor_id', $donor->id)->exists()) {
            return response()->json([
                'message' => 'Lab sudah pernah diproses'
            ], 422);
        }

        return DB::transaction(function () use ($request, $donor) {

            // 🔴 DETEKSI IMLTD
            $isIMLTD =
                $request->hiv === 'reactive' ||
                $request->hcv === 'reactive' ||
                $request->hbsag === 'reactive' ||
                $request->sifilis === 'reactive';

            // 🔴 VALIDASI VITAL
            $isVitalNormal =
                $request->hemoglobin >= 12.5 &&
                $request->systolic >= 90 && $request->systolic <= 140 &&
                $request->diastolic >= 60 && $request->diastolic <= 90 &&
                $request->weight >= 45 &&
                $request->temperature >= 36 && $request->temperature <= 37.5;

            $isEligible = !$isIMLTD && $isVitalNormal;

            LabResult::create([
                'donor_id' => $donor->id,
                'systolic' => $request->systolic,
                'diastolic' => $request->diastolic,
                'hemoglobin' => $request->hemoglobin,
                'weight' => $request->weight,
                'temperature' => $request->temperature,
                'hiv' => $request->hiv,
                'hcv' => $request->hcv,
                'hbsag' => $request->hbsag,
                'sifilis' => $request->sifilis,
                'notes' => $request->notes,
                'is_eligible' => $isEligible,
                'is_imltd' => $isIMLTD
            ]);

            if ($isIMLTD) {
                // 🔴 PERMANENT BLACKLIST
                $donor->update(['is_blacklisted' => true]);

                $donor->queue()->update([
                    'status' => 'rejected'
                ]);
            } elseif (!$isEligible) {

                $donor->queue()->update([
                    'status' => 'rejected'
                ]);
            } else {

                $donor->queue()->update([
                    'status' => 'done'
                ]);
            }

            return response()->json([
                'message' => 'Lab processed',
                'is_eligible' => $isEligible,
                'is_imltd' => $isIMLTD
            ]);
        });
    }
}
