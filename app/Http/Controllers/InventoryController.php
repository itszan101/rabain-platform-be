<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inventory;
use App\Models\Donor;
use App\Models\InventoryCategory;
use Carbon\Carbon;

class InventoryController extends Controller
{
    /**
     * VIEW INVENTORY
     */
    public function index(Request $request)
    {
        $inventories = Inventory::with([
            'donor.bloodType',
            'donor.rhesus',
            'category'
        ])->latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'Inventory list',
            'data' => $inventories
        ]);
    }

    /**
     * ADD INVENTORY (HANDLE 2 CASE)
     * - DROPPING PMI (tanpa donor)
     * - DONOR LANGSUNG
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:inventory_categories,id',
            'donation_date' => 'required|date',
            'status' => 'required|in:available,used,expired',

            'donor_id' => 'nullable|exists:donors,id',
            'blood_type' => 'nullable|in:A,B,AB,O',
            'rhesus' => 'nullable|in:+,-'
        ]);

        $category = InventoryCategory::find($request->category_id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category tidak ditemukan'
            ], 404);
        }

        /**
         * ============================
         * GENERATE BAG ID
         * ============================
         */
        $prefix = match ((int) $category->id) {
            1 => 'DNR', // Donor
            2 => 'PMI', // Dropping PMI
            default => throw new \Exception('Category tidak dikenali untuk bag_id')
        };
        $datePart = Carbon::parse($request->donation_date)->format('ymd');

        $lastNumber = Inventory::whereDate('donation_date', $request->donation_date)
            ->where('bag_id', 'like', $prefix . '-' . $datePart . '-%')
            ->count() + 1;

        $running = str_pad($lastNumber, 4, '0', STR_PAD_LEFT);

        $bagId = "{$prefix}-{$datePart}-{$running}";

        /**
         * ============================
         * HANDLE DATA
         * ============================
         */
        $bloodType = null;
        $rhesus = null;
        $donorId = null;

        // DONOR LANGSUNG
        if ($request->filled('donor_id')) {

            $donor = Donor::with(['bloodType', 'rhesus'])->find($request->donor_id);

            if (!$donor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Donor tidak ditemukan'
                ], 404);
            }

            // fallback mapping
            $bloodType = $donor->bloodType?->name ?? $this->mapBloodType($donor->blood_type_id);
            $rhesus = $donor->rhesus?->name ?? $this->mapRhesus($donor->rhesus_id);

            if (!$bloodType || !$rhesus) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blood type / rhesus donor tidak valid'
                ], 422);
            }

            $donorId = $donor->id;
        }

        // DROPPING PMI
        else {

            if (!$request->filled('blood_type') || !$request->filled('rhesus')) {
                return response()->json([
                    'success' => false,
                    'message' => 'blood_type dan rhesus wajib untuk PMI'
                ], 422);
            }

            $bloodType = $request->blood_type;
            $rhesus = $request->rhesus;
        }

        /**
         * ============================
         * INSERT
         * ============================
         */
        $inventory = Inventory::create([
            'bag_id' => $bagId,
            'donor_id' => $donorId,
            'blood_type' => $bloodType,
            'rhesus' => $rhesus,
            'donation_date' => $request->donation_date,
            'expired_date' => Carbon::parse($request->donation_date)->addDays(35),
            'category_id' => $category->id,
            'status' => $request->status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Inventory berhasil ditambahkan',
            'data' => $inventory
        ], 201);
    }

    /**
     * ============================
     * PREFIX CATEGORY
     * ============================
     */
    private function getPrefix($categoryName)
    {
        return match (strtolower($categoryName)) {
            'Donor' => 'DNR',
            'Dropping PMI' => 'PMI',
            default => 'UNK'
        };
    }

    /**
     * ============================
     * FALLBACK MAPPING
     * ============================
     */
    private function mapBloodType($id)
    {
        return match ((int) $id) {
            1 => 'A',
            2 => 'B',
            3 => 'AB',
            4 => 'O',
            default => null
        };
    }

    private function mapRhesus($id)
    {
        return match ((int) $id) {
            1 => '+',
            2 => '-',
            default => null
        };
    }
}
