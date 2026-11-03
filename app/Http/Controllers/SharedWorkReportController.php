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

        SharedWorkReport::create($validated);

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

        return back()->with('success', 'Wadah KIPAPP berhasil diperbarui!');
    }

    /**
     * Hapus wadah KIPAPP dari modal.
     */
    public function destroy(int $id)
    {
        $report = SharedWorkReport::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $report->delete();

        return back()->with('success', 'Wadah KIPAPP berhasil dihapus!');
    }
}
