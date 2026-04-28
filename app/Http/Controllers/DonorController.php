<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Donor;

class DonorController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
        ]);

        $query = Donor::with([
            'bloodType:id,name',
            'rhesus:id,type', // ✅ FIX DISINI
            'screening',
            'queue',
            'labResult'
        ]);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('nik', 'like', '%' . $request->search . '%')
                    ->orWhere('phone', 'like', '%' . $request->search . '%');
            });
        }

        $donors = $query->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Data donor berhasil diambil',
            'data' => $donors->map(function ($donor) {
                return [
                    'id' => $donor->id,
                    'nik' => $donor->nik,
                    'name' => $donor->name,
                    'birth_date' => $donor->birth_date,
                    'address' => $donor->address,
                    'gender' => $donor->gender,
                    'citizenship' => $donor->citizenship,
                    'phone' => $donor->phone,

                    'blood_type' => $donor->bloodType?->name,
                    'rhesus' => $donor->rhesus?->type, // ✅ FIX DISINI

                    'screening' => $donor->screening,
                    'queue' => $donor->queue,
                    'lab_result' => $donor->labResult,
                ];
            })
        ]);
    }

    public function show($id)
    {
        $donor = Donor::with([
            'bloodType:id,name',
            'rhesus:id,type', // ✅ FIX DISINI
            'screening',
            'queue',
            'labResult'
        ])->find($id);

        if (!$donor) {
            return response()->json([
                'status' => false,
                'message' => 'Donor tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Detail donor',
            'data' => [
                'id' => $donor->id,
                'nik' => $donor->nik,
                'name' => $donor->name,
                'birth_date' => $donor->birth_date,
                'address' => $donor->address,
                'gender' => $donor->gender,
                'citizenship' => $donor->citizenship,
                'phone' => $donor->phone,

                'blood_type' => $donor->bloodType?->name,
                'rhesus' => $donor->rhesus?->type, // ✅ FIX DISINI

                'screening' => $donor->screening,
                'queue' => $donor->queue,
                'lab_result' => $donor->labResult,
            ]
        ]);
    }
}
