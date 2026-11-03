<?php

namespace App\Http\Controllers;

use App\Models\SharedWorkReport;
use App\Services\WorkReportGeneratorService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SharedWorkReportController extends Controller
{
    /**
     * Tampilkan halaman publik berkas bukti dukung kinerja multi-hari.
     */
    public function show(string $uuid)
    {
        $report = SharedWorkReport::where('uuid', $uuid)
            ->where('is_active', true)
            ->with(['user.profile', 'suratTugas'])
            ->firstOrFail();

        // Increment hit counter tanpa mentrigger updated_at
        $report->timestamps = false;
        $report->increment('view_count');
        $report->timestamps = true;

        $activities = $report->getActivities();
        $user = $report->user;

        return view('public.shared-work-report', compact('report', 'activities', 'user'));
    }

    /**
     * Unduh berkas resmi Word (.docx) dari berkas bukti dukung multi-hari.
     */
    public function downloadDocx(string $uuid): BinaryFileResponse
    {
        $report = SharedWorkReport::where('uuid', $uuid)
            ->where('is_active', true)
            ->with(['user.profile', 'suratTugas'])
            ->firstOrFail();

        $activities = $report->getActivities();
        $user = $report->user;

        $generator = app(WorkReportGeneratorService::class);
        $periodLabel = $report->title;

        $result = $generator->generateWordReport($user, $activities, $periodLabel);

        return response()->download($result['path'], $result['name']);
    }

    /**
     * Unduh berkas resmi PDF Buku Bukti Dukung Gabungan.
     */
    public function downloadPdf(string $uuid)
    {
        $report = SharedWorkReport::where('uuid', $uuid)
            ->where('is_active', true)
            ->with(['user.profile', 'suratTugas'])
            ->firstOrFail();

        $activities = $report->getActivities();
        $user = $report->user;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.shared-work-report-book', [
            'report' => $report,
            'activities' => $activities,
            'user' => $user,
        ])->setPaper('a4', 'portrait');

        $safeTitle = \Illuminate\Support\Str::slug($report->title) ?: 'bukti-dukung';
        $fileName = "BUKTI_DUKUNG_{$safeTitle}_" . date('Ymd') . ".pdf";

        return $pdf->download($fileName);
    }

    /**
     * Generate ringkasan capaian kinerja bulanan/triwulanan untuk SKP via Gemini AI.
     */
    public function generateAiSummary(int $id)
    {
        $report = SharedWorkReport::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $activities = $report->getActivities();
        if ($activities->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Wadah ini belum memiliki catatan kegiatan untuk dirangkum.'
            ], 422);
        }

        try {
            $aiService = app(\App\Services\DailyActivityAiService::class);
            $summary = $aiService->generateMonthlySummary($activities, $report->title);

            return response()->json([
                'success' => true,
                'title' => $report->title,
                'summary' => $summary,
                'total_activities' => $activities->count(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal merangkum dengan AI: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tambah wadah KIPAPP baru dari modal.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'tag_slug' => 'nullable|string|max:64',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['is_active'] = true;
        $validated['activity_ids'] = [];

        $report = SharedWorkReport::create($validated);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'report' => $report]);
        }

        return back()->with('success', 'Wadah KIPAPP berhasil dibuat!');
    }

    /**
     * Update wadah KIPAPP dari modal.
     */
    public function update(Request $request, int $id)
    {
        $report = SharedWorkReport::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'tag_slug' => 'nullable|string|max:64',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        $report->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'report' => $report]);
        }

        return back()->with('success', 'Wadah KIPAPP berhasil diperbarui!');
    }

    /**
     * Hapus wadah KIPAPP dari modal.
     */
    public function destroy(Request $request, int $id)
    {
        $report = SharedWorkReport::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $report->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Wadah KIPAPP berhasil dihapus!');
    }
}
