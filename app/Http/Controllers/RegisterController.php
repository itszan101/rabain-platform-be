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
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

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
        $existingDonor = Donor::where('nik', $request->nik)
            ->latest('created_at')
            ->first();

        if ($existingDonor) {
            // 🔴 CEK IMLTD
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

            // 🔴 CEK 60 HARI
            $nextEligibleDate = Carbon::parse($existingDonor->created_at)->addDays(60);
            if (Carbon::now()->lt($nextEligibleDate)) {
                return response()->json([
                    'message' => 'Calon Pendonor boleh mendonor lagi pada tanggal ' .
                        $nextEligibleDate->format('d-m-Y')
                ], 422);
            }
        }

        // 🔴 PROSES REGISTRASI BARU
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
    public function lookupByNik(Request $request): JsonResponse
    {
        $request->validate([
            'nik' => 'required|string'
        ]);

        // 🔴 Ambil data terakhir berdasarkan created_at
        $donor = Donor::where('nik', $request->nik)
            ->latest('created_at')
            ->first();

        if (!$donor) {
            return response()->json([
                'message' => 'Data pendonor tidak ditemukan, Silahkan isi data diri secara manual'
            ], 404);
        }

        return response()->json([
            'message' => 'Data ditemukan',
            'data' => [
                'nik' => $donor->nik,
                'name' => $donor->name,
                'birth_date' => $donor->birth_date,
                'address' => $donor->address,
                'gender' => $donor->gender,
                'citizenship' => $donor->citizenship,
                'blood_type_id' => $donor->blood_type_id,
                'rhesus_id' => $donor->rhesus_id,
                'phone' => $donor->phone,
            ]
        ]);
    }

    public function ocrKtp(Request $request)
    {
        if (!$request->hasFile('image')) {
            return response()->json([
                'status' => false,
                'message' => 'File tidak diterima backend'
            ], 400);
        }

        $request->validate([
            'image' => 'required|image'
        ]);

        // 🔴 SIMPAN FILE DI BACKEND
        $filename = Str::uuid() . '.jpg';
        $destination = storage_path('app/ktp');

        if (!file_exists($destination)) {
            mkdir($destination, 0777, true);
        }

        $request->file('image')->move($destination, $filename);

        $fullPath = $destination . '/' . $filename;

        if (!file_exists($fullPath)) {
            return response()->json([
                'status' => false,
                'message' => 'File gagal disimpan'
            ], 500);
        }

        // 🔴 OCR TESSERACT
        $text = shell_exec("tesseract " . escapeshellarg($fullPath) . " stdout 2>&1");

        if (!$text || str_contains($text, 'Error')) {
            return response()->json([
                'status' => false,
                'raw' => $text,
                'message' => 'Tesseract gagal membaca file'
            ], 500);
        }

        // 🔴 PARSING
        $data = [
            'nik' => null,
            'name' => null,
            'birth_date' => null,
            'address' => null,
            'gender' => null,
            'blood_type_id' => null,
            'citizenship' => 'WNI'
        ];

        if (preg_match('/\b\d{16}\b/', $text, $m)) {
            $data['nik'] = $m[0];
        }

        if (preg_match('/Nama\s*:\s*(.+)/i', $text, $m)) {
            $data['name'] = trim($m[1]);
        }

        if (preg_match('/(\d{2}-\d{2}-\d{4})/', $text, $m)) {
            $data['birth_date'] = date('Y-m-d', strtotime($m[1]));
        }

        if (stripos($text, 'Perempuan') !== false) {
            $data['gender'] = 'P';
        } elseif (stripos($text, 'Laki') !== false) {
            $data['gender'] = 'L';
        }

        if (preg_match('/Gol.*Darah\s*:\s*([ABO]+)/i', $text, $m)) {
            $map = ['A' => 1, 'B' => 2, 'AB' => 3, 'O' => 4];
            $data['blood_type_id'] = $map[strtoupper($m[1])] ?? 5;
        }

        if (preg_match('/Alamat\s*:\s*(.+)/i', $text, $m)) {
            $data['address'] = trim($m[1]);
        }

        return response()->json([
            'status' => true,
            'raw' => $text,
            'data' => $data
        ]);
    }
}
