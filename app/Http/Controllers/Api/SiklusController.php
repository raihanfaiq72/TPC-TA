<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kolam;
use App\Models\SiklusKolam;
use App\Services\KolamCycleService;
use Illuminate\Http\JsonResponse;

class SiklusController extends Controller
{
    public function __construct(private KolamCycleService $siklus) {}

    public function index(int $kolamId): JsonResponse
    {
        if (! Kolam::query()->whereKey($kolamId)->exists()) {
            return response()->json(['message' => 'Kolam tidak ditemukan.'], 404);
        }

        return response()->json(
            SiklusKolam::query()
                ->where('kolam_id', $kolamId)
                ->orderBy('urutan')
                ->get(),
        );
    }

    public function next(int $kolamId): JsonResponse
    {
        $kolam = Kolam::query()->find($kolamId);

        if (! $kolam instanceof Kolam) {
            return response()->json(['message' => 'Kolam tidak ditemukan.'], 404);
        }

        $hasil = $this->siklus->advance($kolam);
        $siklus = $hasil['siklus'];

        return response()->json([
            'selesai' => $hasil['selesai'],
            'siklus' => $siklus,
            'bobot_target_panen_g' => $siklus instanceof SiklusKolam
                ? (float) $siklus->target_bobot_g
                : null,
            'jumlah_tebar' => $siklus instanceof SiklusKolam
                ? (int) $siklus->jumlah_tebar
                : null,
            'message' => $hasil['selesai']
                ? 'Semua siklus selesai.'
                : 'Siklus berikutnya dimulai.',
        ]);
    }
}
