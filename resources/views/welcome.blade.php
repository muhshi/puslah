<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'DINAMIT BPS Demak') }}</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        bps: {
                            blue: '#005596',
                            orange: '#F2911B',
                            green: '#6CBE45',
                            dark: '#0A2540',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .hero-pattern {
            background-color: #f8fafc;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23e2e8f0' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
    </style>
</head>

<body class="antialiased font-sans text-slate-800 bg-slate-50">

    <!-- Navbar -->
    <nav
        class="fixed w-full z-20 top-0 transition-all duration-300 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <!-- SVG Logo Placeholder -->
                    <div
                        class="w-10 h-10 bg-bps-blue rounded-lg flex items-center justify-center text-white font-bold shadow-lg shadow-blue-500/20">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-bps-dark tracking-tight leading-none">DINAMIT</h1>
                        <p class="text-[10px] font-medium text-bps-orange uppercase tracking-wider">BPS Kabupaten Demak
                        </p>
                    </div>
                </div>

                <!-- Auth Buttons -->
                <div class="flex items-center gap-4">
                    @if (Route::has('filament.admin.auth.login'))
                        @auth
                            <a href="{{ route('filament.admin.pages.dashboard') }}"
                                class="text-sm font-semibold text-bps-blue hover:text-blue-700 transition">Dashboard &rarr;</a>
                        @else
                            <a href="{{ route('filament.admin.auth.login') }}"
                                class="px-5 py-2.5 rounded-full bg-bps-blue text-white text-sm font-medium hover:bg-blue-700 transition shadow-lg shadow-blue-600/20 hover:shadow-xl hover:-translate-y-0.5 transform duration-200">
                                Login Pegawai
                            </a>
                        @endauth
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden hero-pattern">

        <!-- Background Blobs -->
        <div class="absolute top-0 right-0 -mr-20 -mt-20 w-96 h-96 bg-bps-blue/10 rounded-full blur-3xl opacity-50">
        </div>
        <div class="absolute bottom-0 left-0 -ml-20 -mb-20 w-72 h-72 bg-bps-orange/10 rounded-full blur-3xl opacity-50">
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">

            <div
                class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-50 border border-blue-100 text-bps-blue text-xs font-semibold mb-6 animate-fade-in-up">
                <span class="relative flex h-2 w-2">
                    <span
                        class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
                </span>
                Sistem Administrasi Terintegrasi
            </div>

            <h1 class="text-4xl md:text-6xl font-extrabold text-bps-dark tracking-tight mb-6">
                Kelola Administrasi <br class="hidden md:block" />
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-bps-blue to-blue-400">Lebih Cepat &
                    Efisien</span>
            </h1>

            <p class="mt-4 max-w-2xl mx-auto text-lg text-slate-600 mb-10 leading-relaxed">
                Platform digital resmi BPS Kabupaten Demak untuk pengelolaan Surat Tugas, Laporan Perjalanan Dinas, dan
                Administrasi Pegawai & Mitra secara real-time.
            </p>

            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <a href="{{ route('filament.admin.auth.login') }}"
                    class="px-8 py-3.5 rounded-full bg-bps-blue text-white font-semibold text-lg shadow-xl shadow-blue-600/20 hover:bg-blue-700 hover:-translate-y-1 transition transform duration-200 flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                        stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                    </svg>
                    Akses Sistem
                </a>
                <a href="#fitur"
                    class="px-8 py-3.5 rounded-full bg-white text-slate-700 border border-slate-200 font-semibold text-lg hover:bg-slate-50 hover:border-slate-300 transition flex items-center justify-center gap-2">
                    Lihat Fitur ↓
                </a>
            </div>

        </div>
    </div>

    <!-- Features Section -->
    <div id="fitur" class="py-24 bg-white relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-bps-dark">Layanan Unggulan</h2>
                <p class="mt-4 text-slate-500">Meningkatkan produktivitas kinerja BPS Kabupaten Demak</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Feature 1: Surat Tugas -->
                <div
                    class="group p-8 rounded-2xl bg-white border border-slate-100 shadow-xl shadow-slate-200/50 hover:shadow-2xl hover:shadow-blue-500/10 transition duration-300 hover:-translate-y-1">
                    <div
                        class="w-14 h-14 bg-blue-50 rounded-xl flex items-center justify-center mb-6 text-bps-blue group-hover:scale-110 transition duration-300">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-8 h-8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-800 mb-3">E-Surat Tugas</h3>
                    <p class="text-slate-600 leading-relaxed">
                        Generate Surat Tugas secara massal atau perorangan dengan penomoran otomatis yang terintegrasi.
                    </p>
                </div>

                <!-- Feature 2: Laporan Dinas -->
                <div
                    class="group p-8 rounded-2xl bg-white border border-slate-100 shadow-xl shadow-slate-200/50 hover:shadow-2xl hover:shadow-orange-500/10 transition duration-300 hover:-translate-y-1">
                    <div
                        class="w-14 h-14 bg-orange-50 rounded-xl flex items-center justify-center mb-6 text-bps-orange group-hover:scale-110 transition duration-300">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-8 h-8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zNM18.75 10.5h.008v.008h-.008V10.5z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-800 mb-3">Laporan Dinas Digital</h3>
                    <p class="text-slate-600 leading-relaxed">
                        Dokumentasi perjalanan dinas lengkap dengan upload foto dan export dokumen Word otomatis.
                    </p>
                </div>

                <!-- Feature 3: Data Terpusat -->
                <div
                    class="group p-8 rounded-2xl bg-white border border-slate-100 shadow-xl shadow-slate-200/50 hover:shadow-2xl hover:shadow-green-500/10 transition duration-300 hover:-translate-y-1">
                    <div
                        class="w-14 h-14 bg-green-50 rounded-xl flex items-center justify-center mb-6 text-bps-green group-hover:scale-110 transition duration-300">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-8 h-8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-800 mb-3">Database Terpusat</h3>
                    <p class="text-slate-600 leading-relaxed">
                        Pengelolaan data profil, presensi, dan kinerja mitra dalam satu sistem yang terintegrasi.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="bg-bps-dark py-16 text-white border-t border-bps-blue/30 relative overflow-hidden">

        <!-- Decoration lines -->
        <div class="absolute inset-0 opacity-10"
            style="background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, #fff 10px, #fff 11px);">
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                <div>
                    <div class="text-4xl font-extrabold text-bps-orange mb-2">{{ \App\Models\User::count() }}</div>
                    <div class="text-blue-200 text-sm uppercase tracking-wider font-semibold">Total User</div>
                </div>
                <div>
                    <div class="text-4xl font-extrabold text-bps-orange mb-2">{{ \App\Models\SuratTugas::count() }}
                    </div>
                    <div class="text-blue-200 text-sm uppercase tracking-wider font-semibold">Surat Tugas</div>
                </div>
                <div>
                    <div class="text-4xl font-extrabold text-bps-orange mb-2">
                        {{ \App\Models\LaporanPerjalananDinas::count() }}
                    </div>
                    <div class="text-blue-200 text-sm uppercase tracking-wider font-semibold">Laporan Dinas</div>
                </div>
                <div>
                    <div class="text-4xl font-extrabold text-bps-orange mb-2">
                        {{ \App\Models\Survey::where('is_active', true)->count() }}
                    </div>
                    <div class="text-blue-200 text-sm uppercase tracking-wider font-semibold">Survey Aktif</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white py-12 border-t border-slate-200 relative">
        <div
            class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="text-center md:text-left">
                <span class="block text-xl font-bold text-bps-dark">DINAMIT</span>
                <span class="text-slate-500 text-sm">Badan Pusat Statistik Kabupaten Demak</span>
            </div>

            <div class="text-slate-500 text-sm text-center md:text-right">
                &copy; {{ date('Y') }} DINAMIT BPS Demak. All rights reserved.<br>
                <span class="text-slate-400 text-xs">Jalan Sultan Fatah No. 10, Demak</span>
            </div>
        </div>
    </footer>

</body>

</html>