<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report->title }} | Bukti Dukung BPS Kabupaten Demak</title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        bps: {
                            blue: '#0284c7',
                            navy: '#0f172a',
                            orange: '#f97316',
                            emerald: '#059669',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen antialiased selection:bg-sky-500 selection:text-white"
      x-data="{
          copied: false,
          activePhoto: null,
          copyUrl() {
              navigator.clipboard.writeText(window.location.href);
              this.copied = true;
              setTimeout(() => this.copied = false, 2500);
          }
      }">

    {{-- Header Banner Instansi --}}
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm/50 backdrop-blur-md bg-white/95">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3.5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <img src="/images/logo_bps.png" alt="Logo BPS" class="h-9 w-auto object-contain" onerror="this.src='/images/logo.png'">
                <div>
                    <h1 class="text-xs font-bold uppercase tracking-wider text-slate-500">Badan Pusat Statistik</h1>
                    <p class="text-sm font-extrabold text-slate-800">BPS KABUPATEN DEMAK</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                {{-- Tombol Salin Tautan --}}
                <button
                    @click="copyUrl()"
                    type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 sm:px-3.5 sm:py-2 text-xs font-semibold rounded-lg bg-sky-50 text-sky-700 hover:bg-sky-100 border border-sky-200 transition-colors shadow-sm"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                    </svg>
                    <span x-text="copied ? 'Tersalin! ✅' : 'Salin Tautan'">Salin Tautan</span>
                </button>

                {{-- Tombol Unduh PDF --}}
                <a
                    href="{{ route('shared-work-report.download-pdf', ['uuid' => $report->uuid]) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 sm:px-3.5 sm:py-2 text-xs font-semibold rounded-lg bg-rose-600 text-white hover:bg-rose-700 transition-colors shadow-sm"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    <span>Unduh PDF</span>
                </a>

                {{-- Tombol Unduh Word --}}
                <a
                    href="{{ route('shared-work-report.download', ['uuid' => $report->uuid]) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 sm:px-3.5 sm:py-2 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-colors shadow-sm"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span>Unduh Word</span>
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 py-8">
        {{-- Hero Header Berkas --}}
        <div class="bg-gradient-to-br from-slate-900 via-sky-950 to-slate-900 text-white rounded-2xl p-6 sm:p-8 shadow-xl mb-8 relative overflow-hidden border border-slate-800">
            <div class="absolute -right-12 -top-12 w-64 h-64 bg-sky-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-sky-500/20 border border-sky-400/30 text-sky-300 text-xs font-semibold mb-4">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    BERKAS RESMI BUKTI DUKUNG MULTI-HARI
                </div>

                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white mb-3">
                    {{ $report->title }}
                </h2>

                @if(!empty($report->description))
                    <p class="text-slate-300 text-sm leading-relaxed max-w-3xl mb-4">
                        {{ $report->description }}
                    </p>
                @endif

                {{-- Metadata Grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 border-t border-white/10 text-xs">
                    <div>
                        <span class="text-slate-400 block mb-0.5">Pegawai Pelaksana:</span>
                        <span class="font-bold text-white text-sm">{{ $user->name }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block mb-0.5">NIP:</span>
                        <span class="font-mono text-slate-200">{{ $user->profile?->nip ?: $user->email }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block mb-0.5">Total Kegiatan:</span>
                        <span class="font-bold text-amber-300">{{ $activities->count() }} Hari Pelaksanaan</span>
                    </div>
                    <div>
                        <span class="text-slate-400 block mb-0.5">Surat Tugas:</span>
                        <span class="font-bold text-emerald-300">
                            {{ $report->suratTugas ? 'No. ' . ($report->suratTugas->nomor_surat_tugas ?: 'DRAFT') : 'Penugasan Rutin/Mandiri' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Notice untuk Pemeriksa E-Kinerja --}}
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-8 flex items-start gap-3 text-xs text-blue-900 shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <strong class="font-bold">Informasi Verifikasi Bukti Dukung:</strong> Halaman ini merupakan tautan berkas resmi kinerja pegawai BPS Kabupaten Demak yang mencakup dokumentasi terpadu untuk pekerjaan multi-hari (baik rentang tanggal berurutan maupun melompat). Setiap foto telah dilengkapi stempel waktu dan koordinat GPS asli saat pelaksanaan tugas.
            </div>
        </div>

        {{-- Daftar Hari & Kegiatan (Timeline) --}}
        <div class="space-y-8">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <span>🗓️</span> Rincian Pelaksanaan Tugas & Bukti Visual
                </h3>
                <span class="text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-1 rounded-full">
                    {{ $activities->count() }} Catatan Kegiatan
                </span>
            </div>

            @forelse($activities as $index => $act)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden hover:border-slate-300 transition-all">
                    {{-- Header Tanggal Kegiatan --}}
                    <div class="bg-slate-50 px-5 py-3 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <span class="w-7 h-7 rounded-lg bg-sky-600 text-white font-bold text-xs flex items-center justify-center shadow-sm">
                                {{ $index + 1 }}
                            </span>
                            <div>
                                <span class="text-xs text-slate-500 font-medium block">Tanggal Pelaksanaan:</span>
                                <h4 class="text-sm font-bold text-slate-900">
                                    {{ \Carbon\Carbon::parse($act->activity_date)->translatedFormat('l, d F Y') }}
                                </h4>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            @if($act->suratTugas)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <span>🔖 ST:</span> {{ $act->suratTugas->nomor_surat_tugas ?: 'Draft' }}
                                </span>
                            @endif
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-sky-50 text-sky-700 border border-sky-200">
                                <span>📷 {{ $act->attachments->count() }} Foto</span>
                            </span>
                        </div>
                    </div>

                    {{-- Konten Narasi --}}
                    <div class="p-5">
                        <div class="mb-5">
                            <h5 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Uraian Realisasi Pekerjaan</h5>
                            <div class="text-sm text-slate-800 leading-relaxed bg-slate-50/70 p-3.5 rounded-lg border border-slate-100">
                                {!! nl2br(e($act->final_content ?: $act->raw_content)) !!}
                            </div>
                        </div>

                        @php
                            $photos = $act->attachments->filter(fn($a) => !str_ends_with(strtolower($a->file_path), '.pdf'));
                            $pdfs = $act->attachments->filter(fn($a) => str_ends_with(strtolower($a->file_path), '.pdf'));
                        @endphp

                        {{-- Galeri Foto Dokumentasi Berstempel --}}
                        @if($photos->isNotEmpty())
                            <div class="mb-5">
                                <h5 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
                                    <span>📸</span> Dokumentasi Foto Berstempel Koordinat & Waktu ({{ $photos->count() }})
                                </h5>

                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                    @foreach($photos as $photo)
                                        @php
                                            $photoUrl = $photo->url ?: asset('storage/' . ltrim($photo->file_path, '/'));
                                            $lat = $photo->latitude ?: -6.894561;
                                            $lng = $photo->longitude ?: 110.637492;
                                            $timeStr = $photo->captured_at ? $photo->captured_at->translatedFormat('d M Y H:i') . ' WIB' : '-';
                                            $captionText = $photo->caption ?: 'Dokumentasi Kegiatan Lapangan';
                                        @endphp
                                        <div
                                            @click="activePhoto = {
                                                url: '{{ $photoUrl }}',
                                                caption: '{{ addslashes($captionText) }}',
                                                lat: '{{ $lat }}',
                                                lng: '{{ $lng }}',
                                                time: '{{ $timeStr }}'
                                            }"
                                            class="group relative rounded-xl overflow-hidden border border-slate-200 bg-slate-900 cursor-pointer shadow-sm hover:shadow-md transition-all flex flex-col"
                                        >
                                            <div class="relative w-full aspect-video sm:aspect-square bg-slate-950 flex items-center justify-center overflow-hidden">
                                                <img
                                                    src="{{ $photoUrl }}"
                                                    alt="{{ $captionText }}"
                                                    loading="lazy"
                                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                                />

                                                {{-- Overlay Stempel Pojok Kiri Bawah (Mirip OpenCamera) --}}
                                                <div class="absolute bottom-2 left-2 right-2 bg-black/80 backdrop-blur-[2px] p-2 rounded-lg border border-white/20 text-white pointer-events-none text-[9px] font-mono leading-tight space-y-0.5">
                                                    <div class="text-yellow-300 font-bold truncate">
                                                        📍 Lat: {{ number_format($lat, 6) }} Long: {{ number_format($lng, 6) }}
                                                    </div>
                                                    <div class="text-yellow-200 truncate">
                                                        🕒 {{ $timeStr }}
                                                    </div>
                                                    <div class="text-white font-medium truncate">
                                                        📝 {{ $captionText }}
                                                    </div>
                                                    <div class="text-emerald-400 font-bold tracking-wider pt-0.5 text-[8px]">
                                                        🏛️ BPS KABUPATEN DEMAK
                                                    </div>
                                                </div>
                                            </div>

                                            @if($photo->caption)
                                                <div class="p-2.5 bg-white text-xs text-slate-700 font-medium border-t border-slate-100 truncate">
                                                    {{ $photo->caption }}
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Daftar Dokumen PDF Bukti Dukung --}}
                        @if($pdfs->isNotEmpty())
                            <div>
                                <h5 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
                                    <span>📄</span> Dokumen Kelengkapan PDF ({{ $pdfs->count() }})
                                </h5>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach($pdfs as $pdf)
                                        @php
                                            $pdfUrl = $pdf->url ?: asset('storage/' . ltrim($pdf->file_path, '/'));
                                            $pdfTitle = $pdf->caption ?: ($pdf->file_name ?: 'Dokumen Bukti Dukung PDF');
                                        @endphp
                                        <div class="p-3.5 rounded-xl border border-red-200 bg-red-50/40 hover:bg-red-50 transition-colors flex items-center justify-between gap-3 shadow-xs">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <div class="w-10 h-10 rounded-lg bg-red-600 text-white flex items-center justify-center flex-shrink-0 shadow-sm font-bold text-xs uppercase">
                                                    PDF
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="text-xs font-bold text-slate-900 truncate">{{ $pdfTitle }}</div>
                                                    <div class="text-[11px] text-slate-500 truncate mt-0.5">
                                                        {{ $pdf->file_name ?: 'Berkas Dokumen' }} &bull; {{ $pdf->file_size ? number_format($pdf->file_size / 1024, 0) . ' KB' : 'PDF' }}
                                                    </div>
                                                </div>
                                            </div>

                                            <a
                                                href="{{ $pdfUrl }}"
                                                target="_blank"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-semibold shadow-xs flex-shrink-0 transition-colors"
                                            >
                                                <span>Buka</span>
                                                <span>↗</span>
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($act->attachments->isEmpty())
                            <div class="text-xs text-slate-400 italic">
                                Belum ada berkas bukti dukung yang dilampirkan pada tanggal ini.
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-xl p-12 text-center border border-slate-200">
                    <p class="text-slate-500 text-sm">Tidak ada catatan kegiatan dalam berkas ini.</p>
                </div>
            @endforelse
        </div>

        {{-- Footer Instansi --}}
        <footer class="mt-12 pt-6 border-t border-slate-200 text-center text-xs text-slate-400 space-y-1">
            <p class="font-semibold text-slate-600">Sistem Informasi Bukti Dukung Kinerja Pegawai (PUSLAH)</p>
            <p>Badan Pusat Statistik Kabupaten Demak &bull; Jl. Sultan Hadiwijaya No. 25 Demak</p>
            <p class="text-[11px] text-slate-400">UUID Berkas: <span class="font-mono text-slate-500">{{ $report->uuid }}</span> &bull; Dilihat: {{ $report->view_count }} kali</p>
        </footer>
    </main>

    {{-- Lightbox Modal Zoom Foto (Alpine.js) --}}
    <div
        x-show="activePhoto"
        x-cloak
        @keydown.escape.window="activePhoto = null"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm"
    >
        <div
            @click.outside="activePhoto = null"
            class="relative max-w-4xl w-full bg-slate-900 rounded-2xl overflow-hidden shadow-2xl border border-slate-700 text-white"
        >
            <button
                @click="activePhoto = null"
                type="button"
                class="absolute top-3 right-3 z-20 w-8 h-8 rounded-full bg-black/60 hover:bg-black text-white flex items-center justify-center transition-colors"
            >
                ✕
            </button>

            <div class="flex items-center justify-center bg-black max-h-[75vh] overflow-hidden">
                <img
                    :src="activePhoto ? activePhoto.url : ''"
                    :alt="activePhoto ? activePhoto.caption : ''"
                    class="max-h-[75vh] w-auto object-contain mx-auto"
                />
            </div>

            <div class="p-4 bg-slate-900/95 border-t border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div>
                    <h5 class="font-bold text-sm text-white" x-text="activePhoto ? activePhoto.caption : ''"></h5>
                    <div class="text-slate-400 font-mono mt-0.5 space-x-3">
                        <span>📍 <span x-text="activePhoto ? activePhoto.lat + ', ' + activePhoto.lng : ''"></span></span>
                        <span>🕒 <span x-text="activePhoto ? activePhoto.time : ''"></span></span>
                    </div>
                </div>
                <a
                    :href="activePhoto ? activePhoto.url : '#'"
                    target="_blank"
                    class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 border border-slate-600 rounded-lg text-xs font-semibold text-white transition-colors"
                >
                    Buka Resolusi Penuh ↗
                </a>
            </div>
        </div>
    </div>

</body>
</html>
