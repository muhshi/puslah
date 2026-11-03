# KIPAPP Evidence System Enhancements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enhance the Puslah KIPAPP & Daily Activity ecosystem with voice note transcription via Telegram, public link one-click copying from table badges, table container filtering, AI monthly SKP executive summary generator, PDF merged evidence book download, and public page lightbox gallery.

**Architecture:** 
1. Telegram Bot voice handler accepts OGG audio, downloads it, and routes to Gemini Multimodal Audio API via `DailyActivityAiService`.
2. Filament table gets a custom `SelectFilter` for Wadah KIPAPP and interactive clipboard-copy badges in `activity-wadah-badges.blade.php`.
3. `SharedWorkReportController` gets AI monthly synthesis (`generateAiSummary`) and PDF report compilation using `dompdf`.
4. Public share Blade view gets an Alpine.js photo modal lightbox for high-res evidence inspection.

**Tech Stack:** Laravel 11, Filament v3, Nutgram (Telegram Bot), Google Gemini 2.5 Flash (Multimodal Audio & Text), barryvdh/laravel-dompdf, Alpine.js, TailwindCSS.

**Spec:** User requirements from chat: filter by container, quick link copy on badge, voice note Telegram recording, AI monthly executive summary for SKP realization box, PDF evidence compilation, and public page mobile/lightbox improvements (excluding progress targets).

---

## Global Constraints

- **DATABASE SAFETY:** Do not drop, truncate, or delete any tables or columns.
- **TENANCY/USER ISOLATION:** All filters, AI summaries, and Telegram operations must strictly enforce `user_id == Auth::id()` or `$user->id`.
- **FALLBACK RESILIENCE:** If Gemini voice transcription or AI summary fails, graceful fallbacks must prevent process crashes or data loss.
- **DESIGN INTEGRITY:** Follow official Filament and Tailwind design guidelines (compact pills, proper padding, zero raw unconstrained SVGs).

---

## Review Focus

1. *Voice note format mismatch:* Telegram sends voice notes as `.oga`/`.ogg` (Opus); ensure Gemini API payload sends `mime_type: audio/ogg` properly formatted as base64.
2. *Empty container activities:* Handling AI summary generation when a container has 0 activities (graceful warning rather than API crash).
3. *Clipboard permission in browser:* One-click copy must support `navigator.clipboard.writeText` with visual feedback (green "Tersalin!").
4. *DOMPDF page breaks and image rendering:* Ensure large attachments in PDF download do not exceed memory limits or break page layouts.
5. *Filter container edge cases:* Filter must handle both "Semua Wadah", specific containers, and "Belum Masuk Wadah".

---

## Tasks

### Task 1: Wadah KIPAPP Filter in Daily Activity Table
**Files:**
- Modify: `app/Filament/Resources/DailyActivityResource.php`

- [ ] Add `SelectFilter` for `shared_work_report` in `DailyActivityResource::table()` filters.
- [ ] Query options: all active containers belonging to `Auth::id()`.
- [ ] Support filtering activities belonging to a selected container via `whereHas('sharedWorkReports', ...)` or legacy `shared_work_report_id`.
- [ ] Add filter option or ternary option for "Belum Ada Wadah" (`whereDoesntHave('sharedWorkReports')->whereNull('shared_work_report_id')`).
- [ ] Verify filter works without performance degradation.

---

### Task 2: Interactive One-Click Link Copy on Table Wadah Badges
**Files:**
- Modify: `resources/views/filament/tables/columns/activity-wadah-badges.blade.php`

- [ ] Update `activity-wadah-badges.blade.php` with Alpine.js `@click.stop` to copy the container's public share URL to clipboard.
- [ ] Show brief feedback state (change icon to checkmark `📋 Tersalin!` for 2 seconds).
- [ ] Include fallback link to open the public page in new tab.
- [ ] Keep badge size compact and elegant.

---

### Task 3: Telegram Voice Note (VN) Input with Gemini Multimodal Audio
**Files:**
- Modify: `app/Services/DailyActivityAiService.php`
- Modify: `app/Telegram/Handlers/DailyActivityTelegramHandler.php`

- [ ] Add `formalizeAudio(string $audioPath, array $context): string` in `DailyActivityAiService.php`.
- [ ] Encode audio file to base64 with `mime_type: 'audio/ogg'` and send to Gemini 2.5 Flash / 1.5 Flash multimodal endpoint.
- [ ] Add `handleVoice(Nutgram $bot)` in `DailyActivityTelegramHandler.php`.
- [ ] Download voice file via Telegram Nutgram bot (`$bot->getFile($fileId)`).
- [ ] Extract active Surat Tugas and hashtags if mentioned in speech or recent context.
- [ ] Create `DailyActivity` with transcribed & formalized text, `source: 'telegram_voice'`.
- [ ] Send confirmation back to Telegram with transcript and formal narrative.
- [ ] Clean up temporary downloaded audio files from storage.

---

### Task 4: AI Monthly Capaian SKP Summary Generator
**Files:**
- Modify: `app/Services/DailyActivityAiService.php`
- Modify: `app/Http/Controllers/SharedWorkReportController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/filament/pages/shared-reports-modal.blade.php`

- [ ] Add `generateMonthlySummary(Collection $activities, string $containerTitle): string` in `DailyActivityAiService.php`.
- [ ] Add controller endpoint `generateAiSummary(int $id)` in `SharedWorkReportController.php`.
- [ ] Register route `POST /bukti-dukung/wadah/{id}/ai-summary` in `routes/web.php`.
- [ ] Add button "✨ Ringkasan SKP (AI)" in modal kebab menu and detail view in `shared-reports-modal.blade.php`.
- [ ] Display modal dialog showing the synthesized 1-2 paragraph executive summary with a 1-click "Salin ke Clipboard" button.

---

### Task 5: PDF Merged Evidence Book Download
**Files:**
- Create: `resources/views/pdf/shared-work-report-book.blade.php`
- Modify: `app/Http/Controllers/SharedWorkReportController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/filament/pages/shared-reports-modal.blade.php`
- Modify: `resources/views/public/shared-work-report.blade.php`

- [ ] Create clean, official PDF template in `resources/views/pdf/shared-work-report-book.blade.php` with BPS Demak header, metadata table, activity chronological log, and embedded stamped photos.
- [ ] Add `downloadPdf(string $uuid)` in `SharedWorkReportController.php` using `Barryvdh\DomPDF\Facade\Pdf`.
- [ ] Register route `GET /bukti-dukung/share/{uuid}/pdf` in `routes/web.php`.
- [ ] Add "Unduh PDF" button in the modal menu and on the public share page.

---

### Task 6: Mobile Lightbox on Public Evidence Page
**Files:**
- Modify: `resources/views/public/shared-work-report.blade.php`

- [ ] Add Alpine.js lightbox modal to `resources/views/public/shared-work-report.blade.php`.
- [ ] Clicking any photo opens the full-screen modal with clean dark overlay, close button, and metadata caption.
- [ ] Support keyboard escape key and swipe/touch friendly mobile layout.

---

### Task 7: Verification, Git Commit, Changelog, and Production Deployment
**Files:**
- Modify: `README.md`

- [ ] Lint all modified PHP and Blade files.
- [ ] Test route registration and artisan schedule/commands.
- [ ] Commit with clean git message and push to `origin/main`.
- [ ] Update `README.md` Changelog with 2026-11-03 entries.
- [ ] Deploy to production server `10.133.21.24`, clear cache, and restart containers.
- [ ] Verify container health and functionality.
