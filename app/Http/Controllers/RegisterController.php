<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Donor;
use App\Models\Screening;
use App\Models\Queue;
use App\Services\QueueService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\LabResult;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'nik' => 'required',
            'name' => 'required',
            'birth_date' => 'required|date',
            'address' => 'required',
            'gender' => 'required|in:L,P',
            'citizenship' => 'required',

            'blood_type_id' => 'required|exists:blood_types,id',
            'rhesus_id' => 'required|exists:rhesuses,id',

            'phone' => 'required',

            'is_healthy' => 'required|boolean',
            'is_taking_medicine' => 'required|boolean',
            'last_donation_date' => 'nullable|date'
        ]);

        // 🔴 CEK NIK SUDAH PERNAH ADA
        $existingDonor = Donor::where('nik', $request->nik)->first();

        if ($existingDonor) {

            $imltd = LabResult::where('donor_id', $existingDonor->id)
                ->where('is_imltd', true)
                ->latest()
                ->first();

            if ($imltd) {
                return response()->json([
                    'message' => 'Pendonor terindikasi pasien IMLTD pada tanggal ' .
                        Carbon::parse($imltd->created_at)->format('d-m-Y')
                ], 422);
            }
        }

        return DB::transaction(function () use ($request) {

            $donor = Donor::create([
                'nik' => $request->nik,
                'name' => $request->name,
                'birth_date' => $request->birth_date,
                'address' => $request->address,
                'gender' => $request->gender,
                'citizenship' => $request->citizenship,
                'blood_type_id' => $request->blood_type_id,
                'rhesus_id' => $request->rhesus_id,
                'phone' => $request->phone
            ]);

            Screening::create([
                'donor_id' => $donor->id,
                'is_healthy' => $request->is_healthy,
                'is_taking_medicine' => $request->is_taking_medicine,
                'last_donation_date' => $request->last_donation_date
            ]);

            $queue = Queue::create([
                'donor_id' => $donor->id,
                'queue_number' => QueueService::generateQueueNumber(),
                'barcode' => QueueService::generateBarcode(),
                'status' => 'waiting'
            ]);

            return response()->json([
                'message' => 'Registrasi berhasil',
                'queue' => $queue
            ]);
        });
    }
}
