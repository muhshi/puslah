<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{{ $ok ? 'Verifikasi Sertifikat Resmi - ' . ($cert->user->profile->full_name ?? $cert->user->name) : 'Sertifikat Tidak Valid' }} | BPS Demak</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #f0f4f9 0%, #e8eef6 50%, #f4f6fa 100%);
            min-height: 100vh;
        }
        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }
        @keyframes pulse-subtle {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.85; }
        }
        .animate-pulse-subtle {
            animation: pulse-subtle 3s infinite ease-in-out;
        }
    </style>
</head>

<body class="text-slate-800 antialiased selection:bg-blue-500 selection:text-white flex flex-col justify-between p-3 sm:p-6 md:p-8">

    {{-- Top Navigation / Header --}}
    <header class="w-full max-w-2xl mx-auto mb-4 sm:mb-6">
        <div class="flex items-center justify-between bg-white/80 backdrop-blur-md px-4 py-3 rounded-2xl shadow-sm border border-slate-200/80">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/logo_bps.png') }}" alt="Logo BPS" class="h-8 sm:h-9 w-auto object-contain" onerror="this.style.display='none'">
                <div>
                    <h2 class="text-xs sm:text-sm font-bold tracking-tight text-slate-800 uppercase leading-none">BPS KABUPATEN DEMAK</h2>
                    <p class="text-[10px] sm:text-xs text-slate-500 mt-0.5">Sistem Verifikasi Sertifikat Digital</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200/80 px-2.5 py-1 rounded-full text-[11px] font-semibold">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Portal Resmi</span>
            </div>
        </div>
    </header>

    {{-- Main Container --}}
    <main class="w-full max-w-2xl mx-auto flex-grow flex items-center justify-center">

        @if (!$ok)
            {{-- ==================== INVALID STATE ==================== --}}
            <div class="w-full bg-white rounded-3xl shadow-xl shadow-slate-200/60 border border-slate-100 overflow-hidden text-center transition-all">
                {{-- Banner Merah --}}
                <div class="bg-gradient-to-br from-rose-500 via-red-600 to-red-700 p-8 text-white relative overflow-hidden">
                    <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#fff_1px,transparent_1px)] [background-size:16px_16px]"></div>
                    <div class="relative z-10">
                        <div class="w-20 h-20 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center mx-auto mb-4 ring-8 ring-white/10">
                            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight">Sertifikat Tidak Valid</h1>
                        <p class="text-white/80 text-sm mt-1 max-w-md mx-auto">Nomor sertifikat tidak terdaftar pada pangkalan data resmi BPS Kabupaten Demak atau telah dicabut.</p>
                    </div>
                </div>

                {{-- Detail Nomor --}}
                <div class="p-6 sm:p-8 space-y-6">
                    <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 text-left">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Nomor yang Dicari</span>
                        <span class="font-mono text-slate-700 font-bold text-base sm:text-lg break-all">
                            {{ $no ?? 'Tidak disertakan' }}
                        </span>
                    </div>

                    <div class="bg-amber-50 border border-amber-200/80 rounded-2xl p-4 text-left flex gap-3 text-amber-800 text-xs sm:text-sm">
                        <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <span class="font-bold block mb-0.5">Perhatian</span>
                            Pastikan Anda memindai kode QR langsung dari sertifikat fisik/dokumen asli yang diterbitkan oleh BPS Kabupaten Demak.
                        </div>
                    </div>

                    <a href="{{ url('/') }}" class="inline-flex items-center justify-center gap-2 w-full py-3 px-4 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold transition shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Kembali ke Beranda
                    </a>
                </div>
            </div>

        @else
            {{-- ==================== VALID STATE ==================== --}}
            @php
                $user = $cert->user;
                $profile = $user->profile ?? null;
                $fullName = $profile->full_name ?? $user->name;
                $avatarPath = $profile->avatar_path ?? null;
                $avatarUrl = $avatarPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($avatarPath) : null;
                $survey = $cert->survey;
                $template = $cert->template ?? \App\Models\CertificateTemplate::where('active', true)->first();
                
                // Inisial jika tidak ada avatar
                $words = explode(' ', trim($fullName));
                $initials = '';
                foreach (array_slice($words, 0, 2) as $w) {
                    $initials .= strtoupper(substr($w, 0, 1));
                }
            @endphp

            <div class="w-full bg-white rounded-3xl shadow-xl shadow-slate-200/70 border border-slate-200/80 overflow-hidden transition-all">

                {{-- Header Badge Terverifikasi --}}
                <div class="bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 px-6 py-6 sm:py-8 text-white relative overflow-hidden">
                    <div class="absolute -right-6 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
                    <div class="absolute -left-10 -top-10 w-32 h-32 bg-emerald-400/20 rounded-full blur-xl"></div>
                    
                    <div class="relative z-10 flex flex-col sm:flex-row items-center sm:items-start gap-4 text-center sm:text-left">
                        <div class="w-16 h-16 sm:w-18 sm:h-18 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center shrink-0 shadow-lg ring-4 ring-white/15 animate-pulse-subtle">
                            <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <div class="inline-flex items-center gap-1.5 bg-white/20 backdrop-blur-md px-3 py-1 rounded-full text-xs font-bold text-white mb-2">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                DOKUMEN SAH & TERVERIFIKASI
                            </div>
                            <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight leading-tight">Sertifikat Elektronik Asli</h1>
                            <p class="text-emerald-100 text-xs sm:text-sm mt-0.5">Diterbitkan secara sah oleh Badan Pusat Statistik Kabupaten Demak</p>
                        </div>
                    </div>
                </div>

                {{-- Content Body --}}
                <div class="p-5 sm:p-7 space-y-6">

                    {{-- 1. Kartu Profil Penerima --}}
                    <div class="bg-gradient-to-br from-slate-50 to-blue-50/40 rounded-2xl p-4 sm:p-5 border border-slate-200/80 flex flex-col sm:flex-row items-center gap-4 text-center sm:text-left">
                        @if ($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="{{ $fullName }}" 
                                class="w-18 h-18 sm:w-20 sm:h-20 rounded-2xl object-cover shadow-md border-2 border-white ring-2 ring-emerald-400 shrink-0">
                        @else
                            <div class="w-18 h-18 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-black text-xl sm:text-2xl flex items-center justify-center shadow-md border-2 border-white ring-2 ring-emerald-400 shrink-0">
                                {{ $initials }}
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Diberikan Kepada</span>
                            <h2 class="text-lg sm:text-xl font-bold text-slate-900 leading-tight truncate">
                                {{ $fullName }}
                            </h2>
                            @if (!empty($profile->nip))
                                <p class="text-xs text-slate-500 font-mono mt-0.5">NIP: {{ $profile->nip }}</p>
                            @endif
                            <div class="mt-2 flex flex-wrap items-center justify-center sm:justify-start gap-2">
                                <span class="bg-blue-100/80 text-blue-800 text-[11px] font-semibold px-2.5 py-0.5 rounded-md border border-blue-200">
                                    Petugas Resmi
                                </span>
                                @if(!empty($profile->instansi ?? $profile->organization))
                                    <span class="bg-slate-100 text-slate-600 text-[11px] px-2.5 py-0.5 rounded-md border border-slate-200">
                                        {{ $profile->instansi ?? $profile->organization }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- 2. Kartu Nama Kegiatan / Survei --}}
                    <div class="bg-slate-50/80 rounded-2xl p-4 sm:p-5 border border-slate-200/80">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Kegiatan / Survei</span>
                                <h3 class="text-sm sm:text-base font-bold text-slate-800 leading-snug">
                                    {{ $survey->name }}
                                </h3>
                                @if($survey->start_date && $survey->end_date)
                                    <div class="flex items-center gap-1.5 text-xs text-slate-500 mt-2 font-medium">
                                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <span>{{ $survey->start_date->translatedFormat('d M Y') }} — {{ $survey->end_date->translatedFormat('d M Y') }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- 3. Detail Metadata Sertifikat (Grid 2 Kolom di Tablet/Desktop) --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        {{-- Nomor Sertifikat --}}
                        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-sm relative group">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Nomor Sertifikat</span>
                                <button onclick="copyToClipboard('{{ $cert->certificate_no }}')" title="Salin Nomor"
                                    class="text-xs text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-2 py-0.5 rounded-md font-medium transition flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                    <span id="copy-btn-text">Salin</span>
                                </button>
                            </div>
                            <p class="font-mono text-sm sm:text-base font-bold text-slate-800 break-all select-all">
                                {{ $cert->certificate_no }}
                            </p>
                        </div>

                        {{-- Tanggal Terbit --}}
                        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-sm">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Tanggal Terbit</span>
                            <p class="text-sm sm:text-base font-bold text-slate-800">
                                {{ $cert->issued_at->translatedFormat('d F Y') }}
                            </p>
                            <p class="text-[11px] text-slate-400 mt-0.5">Tercatat di sistem Puslah BPS</p>
                        </div>

                        {{-- Penandatangan --}}
                        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-sm sm:col-span-2">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Penandatangan Resmi</span>
                                    <p class="text-sm sm:text-base font-bold text-slate-800 leading-snug">
                                        {{ $template->signer_name ?? 'Khomarudin' }}
                                    </p>
                                    <p class="text-xs text-slate-500 mt-0.5 leading-snug">
                                        {{ $template->signer_title ?? 'Kepala Badan Pusat Statistik Kabupaten Demak' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 4. Sidik Jari Kriptografi / SHA-256 Hash --}}
                    @if (!empty($cert->hash))
                        <div class="bg-slate-50/80 rounded-2xl p-4 border border-slate-200 text-xs">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="font-bold text-slate-600 flex items-center gap-1.5 text-xs">
                                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                    Integritas Dokumen (SHA-256)
                                </span>
                                <span class="text-[10px] bg-slate-200/70 text-slate-600 px-2 py-0.5 rounded font-mono">Immutable</span>
                            </div>
                            <p class="font-mono text-[11px] text-slate-500 break-all leading-relaxed bg-white p-2 rounded-lg border border-slate-200/60 select-all">
                                {{ $cert->hash }}
                            </p>
                        </div>
                    @endif

                    {{-- 5. Tombol Unduh PDF (jika login / jika berhak) --}}
                    @auth
                        <div class="pt-2">
                            <a href="{{ route('certificates.download', $cert) }}" 
                                class="w-full flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-bold py-3.5 px-6 rounded-2xl transition shadow-lg shadow-blue-500/25 text-sm sm:text-base">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                Unduh Dokumen PDF Asli
                            </a>
                        </div>
                    @endauth

                </div>

                {{-- Card Footer --}}
                <div class="bg-slate-50 px-6 py-4 border-t border-slate-200/80 text-center">
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Data ini dihasilkan secara otomatis oleh <strong>Puslah BPS Kabupaten Demak</strong>.<br>
                        Keabsahan dokumen ini dapat dipertanggungjawabkan sesuai ketentuan yang berlaku.
                    </p>
                </div>

            </div>
        @endif

    </main>

    {{-- Bottom Copyright --}}
    <footer class="w-full max-w-2xl mx-auto mt-6 text-center text-xs text-slate-400">
        &copy; {{ date('Y') }} Badan Pusat Statistik Kabupaten Demak. All rights reserved.
    </footer>

    {{-- Script Salin Nomor --}}
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                var btnText = document.getElementById('copy-btn-text');
                if (btnText) {
                    btnText.innerText = 'Disalin!';
                    setTimeout(function() {
                        btnText.innerText = 'Salin';
                    }, 2000);
                }
            }).catch(function() {
                alert('Nomor sertifikat: ' + text);
            });
        }
    </script>

</body>

</html>