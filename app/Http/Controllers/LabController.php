<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Donor;
use App\Models\LabResult;
use Illuminate\Support\Facades\DB;
use App\Models\Queue;
use App\Models\Inventory;
use App\Models\InventoryCategory;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Models\BloodType;
use App\Models\Rhesus;

class LabController extends Controller
{
    public function list()
    {
        return Queue::with('donor')
            ->whereIn('status', ['lab', 'diproses', 'done', 'rejected'])
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

            'blood_type' => 'required|in:A,B,O,AB',
            'rhesus' => 'required|in:positive,negative',

            'hiv' => 'required|in:non-reactive,reactive',
            'hcv' => 'required|in:non-reactive,reactive',
            'hbsag' => 'required|in:non-reactive,reactive',
            'sifilis' => 'required|in:non-reactive,reactive',

            'notes' => 'nullable|string'
        ]);

        $donor = Donor::with('queue')->findOrFail($request->donor_id);

        if ($donor->is_blacklisted) {
            return response()->json(['message' => 'Donor sudah blacklist'], 422);
        }

        if (!$donor->queue || $donor->queue->status !== 'Lab') {
            return response()->json([
                'message' => 'Donor belum masuk tahap lab'
            ], 422);
        }

        if (!$donor->queue->barcode) {
            return response()->json([
                'message' => 'Barcode queue belum tersedia'
            ], 422);
        }

        if (LabResult::where('donor_id', $donor->id)->exists()) {
            return response()->json([
                'message' => 'Lab sudah pernah diproses'
            ], 422);
        }

        return DB::transaction(function () use ($request, $donor) {

            $rhesusMap = [
                'positive' => '+',
                'negative' => '-',
            ];

            if (!isset($rhesusMap[$request->rhesus])) {
                throw new \Exception('Invalid rhesus value');
            }

            $rhesusValue = $rhesusMap[$request->rhesus];

            $bloodType = BloodType::where('name', $request->blood_type)->firstOrFail();
            $rhesus    = Rhesus::where('type', $rhesusValue)->firstOrFail();

            $donor->update([
                'blood_type_id' => $bloodType->id,
                'rhesus_id'     => $rhesus->id,
            ]);

            $isIMLTD =
                $request->hiv === 'reactive' ||
                $request->hcv === 'reactive' ||
                $request->hbsag === 'reactive' ||
                $request->sifilis === 'reactive';

            $isVitalNormal =
                $request->hemoglobin >= 12.5 &&
                $request->systolic >= 90 && $request->systolic <= 140 &&
                $request->diastolic >= 60 && $request->diastolic <= 90 &&
                $request->weight >= 45 &&
                $request->temperature >= 36 && $request->temperature <= 37.5;

            $isEligible = !$isIMLTD && $isVitalNormal;

            $donor->update([
                'is_eligible' => $isEligible,
            ]);

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

                $donor->update([
                    'is_blacklisted' => true,
                    'is_eligible' => false,
                ]);

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

                /**
                 * =========================
                 * ✅ FIX BAG ID (ANTI DUPLICATE)
                 * =========================
                 */
                $category = InventoryCategory::where('name', 'Donor')->firstOrFail();

                $prefix = 'DNR';
                $datePart = now()->format('ymd');

                $lastBag = Inventory::where('bag_id', 'like', $prefix . '-' . $datePart . '-%')
                    ->lockForUpdate()
                    ->orderByDesc('id')
                    ->first();

                if ($lastBag) {
                    $lastRunning = (int) substr($lastBag->bag_id, -4);
                    $nextRunning = $lastRunning + 1;
                } else {
                    $nextRunning = 1;
                }

                $running = str_pad($nextRunning, 4, '0', STR_PAD_LEFT);

                $bagId = "{$prefix}-{$datePart}-{$running}";

                /**
                 * =========================
                 * INSERT INVENTORY
                 * =========================
                 */
                Inventory::create([
                    'bag_id' => $bagId,
                    'donor_id' => $donor->id,
                    'blood_type' => $bloodType->name,
                    'rhesus' => $rhesusValue,
                    'donation_date' => now(),
                    'expired_date' => now()->addDays(35),
                    'category_id' => $category->id,
                    'status' => 'available'
                ]);
            }

            return response()->json([
                'message' => 'Lab processed',
                'is_eligible' => $isEligible,
                'is_imltd' => $isIMLTD
            ]);
        });
    }

    private function mapBlood($id)
    {
        return match ($id) {
            1 => 'A',
            2 => 'B',
            3 => 'AB',
            4 => 'O',
            default => '-'
        };
    }

    private function mapRhesus($id)
    {
        return match ($id) {
            1 => '+',
            2 => '-',
            default => '-'
        };
    }
}
