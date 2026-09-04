<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{{ $ok ? 'Verifikasi Surat Tugas Resmi - ' . ($surat->nomor_surat ?? 'BPS Demak') : 'Dokumen Tidak Valid' }} | BPS Demak</title>
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
                    <p class="text-[10px] sm:text-xs text-slate-500 mt-0.5">Sistem Verifikasi Dokumen Tugas Digital</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 bg-blue-50 text-blue-700 border border-blue-200/80 px-2.5 py-1 rounded-full text-[11px] font-semibold">
                <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                <span>Portal Resmi</span>
            </div>
        </div>
    </header>

    {{-- Main Container --}}
    <main class="w-full max-w-2xl mx-auto flex-grow flex items-center justify-center">

        @if (!$ok)
            {{-- ==================== INVALID STATE ==================== --}}
            <div class="w-full bg-white rounded-3xl shadow-xl shadow-slate-200/60 border border-slate-100 overflow-hidden text-center transition-all">
                <div class="bg-gradient-to-br from-rose-500 via-red-600 to-red-700 p-8 text-white relative overflow-hidden">
                    <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#fff_1px,transparent_1px)] [background-size:16px_16px]"></div>
                    <div class="relative z-10">
                        <div class="w-20 h-20 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center mx-auto mb-4 ring-8 ring-white/10">
                            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight">Dokumen Tidak Valid</h1>
                        <p class="text-white/80 text-sm mt-1 max-w-md mx-auto">Surat Tugas tidak ditemukan pada sistem BPS Kabupaten Demak atau tautan verifikasi rusak.</p>
                    </div>
                </div>

                <div class="p-6 sm:p-8 space-y-6">
                    <div class="bg-amber-50 border border-amber-200/80 rounded-2xl p-4 text-left flex gap-3 text-amber-800 text-xs sm:text-sm">
                        <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <span class="font-bold block mb-0.5">Perhatian</span>
                            Pastikan Anda memindai kode QR langsung dari Surat Tugas resmi yang diterbitkan oleh BPS Kabupaten Demak.
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
                $user = $surat->user;
                $profile = $user->profile ?? null;
                $fullName = $profile->full_name ?? $user->name;
                $avatarPath = $profile->avatar_path ?? null;
                $avatarUrl = $avatarPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($avatarPath) : null;
                
                // Inisial
                $words = explode(' ', trim($fullName));
                $initials = '';
                foreach (array_slice($words, 0, 2) as $w) {
                    $initials .= strtoupper(substr($w, 0, 1));
                }
            @endphp

            <div class="w-full bg-white rounded-3xl shadow-xl shadow-slate-200/70 border border-slate-200/80 overflow-hidden transition-all">

                {{-- Header Badge Terverifikasi --}}
                <div class="bg-gradient-to-r from-blue-700 via-indigo-600 to-blue-800 px-6 py-6 sm:py-8 text-white relative overflow-hidden">
                    <div class="absolute -right-6 -bottom-10 w-40 h-40 bg-white/10 rounded-full blur-2xl"></div>
                    <div class="absolute -left-10 -top-10 w-32 h-32 bg-blue-400/20 rounded-full blur-xl"></div>
                    
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
                                DOKUMEN SAH & TERDAFTAR RESMI
                            </div>
                            <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight leading-tight">Surat Tugas Resmi</h1>
                            <p class="text-blue-100 text-xs sm:text-sm mt-0.5">Diterbitkan oleh Badan Pusat Statistik Kabupaten Demak</p>
                        </div>
                    </div>
                </div>

                {{-- Content Body --}}
                <div class="p-5 sm:p-7 space-y-6">

                    {{-- 1. Kartu Pegawai yang Ditugaskan --}}
                    <div class="bg-gradient-to-br from-slate-50 to-blue-50/40 rounded-2xl p-4 sm:p-5 border border-slate-200/80 flex flex-col sm:flex-row items-center gap-4 text-center sm:text-left">
                        @if ($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="{{ $fullName }}" 
                                class="w-18 h-18 sm:w-20 sm:h-20 rounded-2xl object-cover shadow-md border-2 border-white ring-2 ring-blue-400 shrink-0">
                        @else
                            <div class="w-18 h-18 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-black text-xl sm:text-2xl flex items-center justify-center shadow-md border-2 border-white ring-2 ring-blue-400 shrink-0">
                                {{ $initials }}
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Pegawai Ditugaskan</span>
                            <h2 class="text-lg sm:text-xl font-bold text-slate-900 leading-tight truncate">
                                {{ $fullName }}
                            </h2>
                            <p class="text-xs text-slate-600 font-medium mt-0.5">{{ $surat->jabatan ?? ($profile->jabatan ?? 'Pegawai BPS') }}</p>
                            @if (!empty($profile->nip))
                                <p class="text-[11px] text-slate-500 font-mono mt-0.5">NIP: {{ $profile->nip }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- 2. Detail Tugas / Keperluan --}}
                    <div class="bg-slate-50/80 rounded-2xl p-4 sm:p-5 border border-slate-200/80 space-y-4">
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Keperluan Tugas</span>
                            <p class="text-sm sm:text-base font-semibold text-slate-800 leading-snug">
                                {{ $surat->keperluan }}
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-slate-200/70 text-xs sm:text-sm">
                            @if (!empty($surat->tempat_tugas))
                                <div>
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Tempat / Tujuan Tugas</span>
                                    <span class="font-medium text-slate-700 flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        {{ $surat->tempat_tugas }}
                                    </span>
                                </div>
                            @endif

                            <div>
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Waktu Pelaksanaan</span>
                                <span class="font-medium text-slate-700 flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    @if ($surat->waktu_mulai && $surat->waktu_selesai)
                                        {{ $surat->waktu_mulai->translatedFormat('d M Y') }} — {{ $surat->waktu_selesai->translatedFormat('d M Y') }}
                                    @else
                                        {{ $surat->tanggal ? $surat->tanggal->translatedFormat('d F Y') : '-' }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- 3. Detail Metadata Surat Tugas (Grid 2 Kolom) --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        {{-- Nomor Surat --}}
                        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-sm relative group">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Nomor Surat</span>
                                <button onclick="copyToClipboard('{{ $surat->nomor_surat }}')" title="Salin Nomor"
                                    class="text-xs text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-2 py-0.5 rounded-md font-medium transition flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                    <span id="copy-btn-text">Salin</span>
                                </button>
                            </div>
                            <p class="font-mono text-sm sm:text-base font-bold text-slate-800 break-all select-all">
                                {{ $surat->nomor_surat }}
                            </p>
                        </div>

                        {{-- Tanggal Surat --}}
                        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-sm">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Tanggal Surat</span>
                            <p class="text-sm sm:text-base font-bold text-slate-800">
                                {{ $surat->tanggal ? $surat->tanggal->translatedFormat('d F Y') : '-' }}
                            </p>
                            <p class="text-[11px] text-slate-400 mt-0.5">Tercatat di sistem Puslah BPS</p>
                        </div>

                        {{-- Penandatangan --}}
                        <div class="bg-white p-4 rounded-2xl border border-slate-200/90 shadow-sm sm:col-span-2">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center shrink-0 mt-0.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Pejabat Penandatangan</span>
                                    <p class="text-sm sm:text-base font-bold text-slate-800 leading-snug">
                                        {{ $surat->signer_name }}
                                    </p>
                                    <p class="text-xs text-slate-500 mt-0.5 leading-snug">
                                        {{ $surat->signer_title ?? 'Kepala Badan Pusat Statistik Kabupaten Demak' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 4. Tombol Lihat Dokumen PDF --}}
                    @if (!empty($surat->hash))
                        <div class="pt-2">
                            <a href="{{ route('surat-tugas.verify.pdf', $surat->hash) }}" target="_blank"
                                class="w-full flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-bold py-3.5 px-6 rounded-2xl transition shadow-lg shadow-blue-500/25 text-sm sm:text-base">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Lihat Dokumen PDF Resmi
                            </a>
                        </div>
                    @endif

                </div>

                {{-- Card Footer --}}
                <div class="bg-slate-50 px-6 py-4 border-t border-slate-200/80 text-center">
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Data ini dihasilkan secara otomatis oleh <strong>Puslah BPS Kabupaten Demak</strong>.<br>
                        Keabsahan penugasan ini dapat dipertanggungjawabkan sesuai ketentuan kedinasan.
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
                alert('Nomor surat: ' + text);
            });
        }
    </script>

</body>

</html>