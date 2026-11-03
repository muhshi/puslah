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
}
