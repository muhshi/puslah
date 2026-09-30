<div>
    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-30 bg-white/90 backdrop-blur-md border-b border-slate-200/80 shadow-xs">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Brand / Logo -->
                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/logo_bps.png') }}" alt="Logo BPS Demak" class="h-9 w-auto object-contain">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="font-extrabold text-base tracking-tight text-bps-dark">DINAMIT</span>
                            <span class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded bg-blue-100 text-bps-blue">Presensi</span>
                        </div>
                        <p class="text-[11px] text-slate-500 font-medium hidden sm:block">BPS Kabupaten Demak</p>
                    </div>
                </div>

                <!-- Right Side: Live Clock & Dashboard Button -->
                <div class="flex items-center gap-3">
                    <!-- Live Clock (Hidden on very small screens) -->
                    <div class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-100/80 border border-slate-200 text-xs font-medium text-slate-700">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <span id="live-clock">--:--:-- WIB</span>
                    </div>

                    <!-- User Pill -->
                    <div class="hidden sm:flex items-center gap-2 pl-2 border-l border-slate-200">
                        <div class="w-8 h-8 rounded-full bg-bps-blue text-white font-bold text-xs flex items-center justify-center shadow-xs">
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                        </div>
                        <div class="text-left text-xs">
                            <div class="font-semibold text-slate-800 truncate max-w-[120px]">{{ auth()->user()->name }}</div>
                            <div class="text-[10px] text-slate-500">{{ $userRole }}</div>
                        </div>
                    </div>

                    <!-- Tombol ke Halaman Dashboard -->
                    <a href="{{ url('/admin') }}"
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-slate-800 hover:bg-bps-blue text-white text-xs font-semibold shadow-xs hover:shadow-md transition-all duration-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        <span>Dashboard</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6">

        <!-- Welcome Banner & Quick Info -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-bps-dark via-[#0d3b66] to-bps-blue p-6 sm:p-8 text-white shadow-xl">
            <!-- Background Decorative Blobs -->
            <div class="absolute -top-12 -right-12 w-48 h-48 rounded-full bg-white/10 blur-2xl pointer-events-none"></div>
            <div class="absolute -bottom-8 -left-8 w-40 h-40 rounded-full bg-bps-orange/20 blur-xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-1.5">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 backdrop-blur-md text-white text-xs font-medium border border-white/20">
                        <svg class="w-3.5 h-3.5 text-bps-orange-light" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span>{{ $todayFormatted }}</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Halo, {{ auth()->user()->name }} 👋</h1>
                    <p class="text-slate-200 text-xs sm:text-sm max-w-xl">
                        Pastikan GPS aktif sebelum melakukan presensi harian untuk verifikasi lokasi kehadiran Anda.
                    </p>
                </div>

                <!-- Status Badges & Quick Action -->
                <div class="flex flex-wrap items-center gap-2.5">
                    @if ($ruleBanned)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-red-500/20 border border-red-400 text-red-200 text-xs font-semibold">
                            <span class="w-2 h-2 rounded-full bg-red-400"></span>
                            Akses Presensi Diblokir
                        </span>
                    @elseif ($ruleWfa)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-500/20 border border-emerald-400 text-emerald-200 text-xs font-semibold">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            Mode WFA Aktif
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-500/20 border border-blue-300 text-blue-100 text-xs font-semibold">
                            <span class="w-2 h-2 rounded-full bg-blue-300"></span>
                            Presensi Kantor (WFO)
                        </span>
                    @endif

                    <a href="{{ url('/admin') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/20 hover:bg-white/30 border border-white/30 text-white text-xs font-medium backdrop-blur-md transition">
                        <span>Lihat Menu</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- Success Flash Alert (Tampil setelah berhasil presensi) -->
        @if (session()->has('success'))
            <div class="p-5 sm:p-6 rounded-2xl bg-gradient-to-r from-emerald-50 via-teal-50 to-green-50 border border-emerald-200 shadow-md animate-fade-in">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5">
                        <div class="w-12 h-12 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-md shadow-emerald-600/20">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-emerald-900">Presensi Berhasil Dicatat! 🎉</h3>
                            <p class="text-xs sm:text-sm text-emerald-700 mt-0.5">
                                {{ session('success') }}
                            </p>
                        </div>
                    </div>

                    <!-- Action Button: Ke Dashboard -->
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <a href="{{ url('/admin') }}"
                           class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold shadow-md shadow-emerald-600/25 transition-all duration-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                            <span>Halaman Dashboard</span>
                        </a>
                    </div>
                </div>
            </div>
        @endif

        <!-- Error Flash Alert -->
        @if (session()->has('error'))
            <div class="p-4 sm:p-5 rounded-2xl bg-red-50 border border-red-200 text-red-800 shadow-sm flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-red-100 text-red-600 flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-red-900">Perhatian</h4>
                    <p class="text-xs sm:text-sm text-red-700 mt-0.5">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        <!-- UI Geolocation Warning -->
        @if ($uiWarning)
            <div class="p-4 sm:p-5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 shadow-sm flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-amber-900">Status Lokasi (GPS)</h4>
                    <p class="text-xs sm:text-sm text-amber-800 mt-0.5">{{ $uiWarning }}</p>
                </div>
            </div>
        @endif

        <!-- Information Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-5">
            <!-- Card 1: Ketentuan Lokasi -->
            <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-card hover:shadow-md transition">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-bps-blue flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500 font-medium">Kantor & Jam Kerja</div>
                        <div class="text-sm font-bold text-slate-800 truncate max-w-[180px]">
                            {{ $schedule ? $schedule->office->name : $defaultOfficeName }}
                        </div>
                    </div>
                </div>
                <div class="space-y-1.5 pt-2 border-t border-slate-100 text-xs text-slate-600">
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">Jam Kerja:</span>
                        <span class="font-semibold text-slate-700">{{ $workStart }} – {{ $workEnd }} WIB</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400">Maks. Radius:</span>
                        <span class="font-semibold text-slate-700">{{ $effectiveRadiusM }} meter</span>
                    </div>
                </div>
            </div>

            <!-- Card 2: Jam Masuk (Check-in) -->
            <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-card hover:shadow-md transition">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs text-slate-500 font-medium">Jam Masuk</div>
                            <div class="text-base font-extrabold text-slate-800">
                                {{ $attendance?->start_time ? $attendance->start_time . ' WIB' : '--:--' }}
                            </div>
                        </div>
                    </div>
                    @if ($attendance?->start_time)
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                            Tercatat
                        </span>
                    @else
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600">
                            Belum
                        </span>
                    @endif
                </div>
                <div class="pt-2 border-t border-slate-100 text-[11px] text-slate-500 flex justify-between">
                    <span>Status Presensi</span>
                    <span class="font-medium text-slate-700">
                        {{ $attendance?->start_time ? 'Check-in Selesai' : 'Menunggu Check-in' }}
                    </span>
                </div>
            </div>

            <!-- Card 3: Jam Pulang (Check-out) -->
            <div class="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-card hover:shadow-md transition">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-bps-blue flex items-center justify-center font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs text-slate-500 font-medium">Jam Pulang</div>
                            <div class="text-base font-extrabold text-slate-800">
                                {{ $attendance?->end_time ? $attendance->end_time . ' WIB' : '--:--' }}
                            </div>
                        </div>
                    </div>
                    @if ($attendance?->end_time)
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-blue-100 text-bps-blue border border-blue-200">
                            Tercatat
                        </span>
                    @else
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600">
                            Belum
                        </span>
                    @endif
                </div>
                <div class="pt-2 border-t border-slate-100 text-[11px] text-slate-500 flex justify-between">
                    <span>Status Presensi</span>
                    <span class="font-medium text-slate-700">
                        {{ $attendance?->end_time ? 'Check-out Selesai' : 'Belum Check-out' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Interactive Geotagging & Map Section -->
        <div class="p-6 sm:p-8 rounded-2xl bg-white border border-slate-200/80 shadow-card space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-bps-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span>Peta & Penandaan Lokasi Geotagging</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Area lingkaran merah menandai batas radius kantor (<span class="font-semibold">{{ $effectiveRadiusM }} meter</span>).
                    </p>
                </div>

                <!-- Distance indicator pill -->
                <div id="distance-badge" class="hidden items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                    <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                    <span id="distance-text">Menunggu pemindaian lokasi...</span>
                </div>
            </div>

            <!-- Leaflet Map Container -->
            <div class="relative rounded-2xl overflow-hidden border border-slate-200 shadow-inner bg-slate-100">
                <div id="map" class="w-full" wire:ignore style="height: 380px;"></div>

                <!-- Map Overlay Helper Hint -->
                <div class="absolute bottom-3 left-3 z-[1000] bg-white/95 backdrop-blur-xs px-3 py-1.5 rounded-lg border border-slate-200 shadow-xs text-[11px] text-slate-600 hidden sm:flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-500"></span>
                    <span>Radius Kantor: {{ $effectiveRadiusM }}m</span>
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-blue-600 ml-2"></span>
                    <span>Posisi Anda</span>
                </div>
            </div>

            <!-- Action Controls Form -->
            <div class="pt-2 flex flex-col sm:flex-row items-center justify-between gap-4">
                <!-- Location Tagging Controls -->
                <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                    <!-- Tombol Tag Location -->
                    <button type="button"
                            id="btn-tag"
                            onclick="tagLocation()"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-bps-blue hover:bg-bps-blue-dark text-white text-sm font-semibold shadow-md shadow-blue-500/20 hover:shadow-lg transition-all active:scale-[0.98] cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span id="btn-tag-text">Ambil Lokasi Saya (GPS)</span>
                    </button>

                    <!-- Tombol Submit Presensi (Muncul jika lokasi valid) -->
                    @if ($hasLocation && ($isWithinRadius || $ruleWfa))
                        <form wire:submit.prevent="store" class="w-full sm:w-auto">
                            <button type="submit"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl {{ $attendance?->start_time ? 'bg-bps-orange hover:bg-orange-600 text-white shadow-orange-500/20' : 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-emerald-500/20' }} text-sm font-bold shadow-md hover:shadow-lg transition-all active:scale-[0.98] cursor-pointer animate-bounce-subtle">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span>{{ $attendance?->start_time ? 'Simpan Jam Pulang (Check-out)' : 'Simpan Jam Masuk (Check-in)' }}</span>
                            </button>
                        </form>
                    @endif
                </div>

                <!-- Tombol ke Halaman Dashboard (Selalu Siap) -->
                <div class="w-full sm:w-auto flex justify-end">
                    <a href="{{ url('/admin') }}"
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold transition active:scale-[0.98]">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        <span>Ke Halaman Dashboard</span>
                    </a>
                </div>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="mt-auto py-6 border-t border-slate-200/80 bg-white/60 text-center text-xs text-slate-400">
        <p>&copy; {{ date('Y') }} Badan Pusat Statistik Kabupaten Demak. Sistem Presensi Terintegrasi DINAMIT.</p>
    </footer>

    <!-- Interactive Script -->
    <script>
        let map, marker, userCircle, component;
        let lat, lng;

        const office = [{{ $officeLat }}, {{ $officeLng }}];
        const radius = {{ $effectiveRadiusM }};
        const is_wfa_rule = {{ $ruleWfa ? 'true' : 'false' }};

        // Live Clock
        function updateClock() {
            const clockEl = document.getElementById('live-clock');
            if (!clockEl) return;
            const now = new Date();
            const timeStr = now.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });
            clockEl.textContent = `${timeStr} WIB`;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Initialize Map
        document.addEventListener('livewire:initialized', function() {
            component = @this;

            map = L.map('map', {
                zoomControl: true,
                scrollWheelZoom: true
            }).setView(office, 16);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            // Office Circle
            L.circle(office, {
                color: '#ef4444',
                fillColor: '#f87171',
                fillOpacity: 0.25,
                weight: 2,
                radius: radius
            }).addTo(map);

            // Office Marker
            const officeMarker = L.marker(office).addTo(map);
            officeMarker.bindPopup("Kantor: {{ $schedule ? $schedule->office->name : $defaultOfficeName }} (Radius: " + radius + " m)");
        });

        function tagLocation() {
            const btnTag = document.getElementById('btn-tag');
            const btnText = document.getElementById('btn-tag-text');
            const distBadge = document.getElementById('distance-badge');
            const distText = document.getElementById('distance-text');

            if (!navigator.geolocation) {
                component.set('uiWarning', 'Browser Anda tidak mendukung geolocation.');
                component.set('hasLocation', false);
                return;
            }

            if (btnText) btnText.textContent = 'Mendeteksi Posisi...';
            if (btnTag) btnTag.disabled = true;

            navigator.geolocation.getCurrentPosition((position) => {
                lat = position.coords.latitude;
                lng = position.coords.longitude;
                const accuracy = Math.round(position.coords.accuracy || 0);

                if (marker) map.removeLayer(marker);
                if (userCircle) map.removeLayer(userCircle);

                // User Marker
                marker = L.marker([lat, lng]).addTo(map);
                marker.bindPopup("Posisi Anda (Akurasi: \u00B1" + accuracy + " m)").openPopup();

                // User accuracy circle
                userCircle = L.circle([lat, lng], {
                    color: '#0284c7',
                    fillColor: '#38bdf8',
                    fillOpacity: 0.15,
                    weight: 1,
                    radius: Math.max(accuracy, 10)
                }).addTo(map);

                // Pan to show both user and office
                const group = new L.featureGroup([marker, L.marker(office)]);
                map.fitBounds(group.getBounds().pad(0.3));

                const okBase = isWithinRadiusBase(lat, lng, office, radius);
                const ok = okBase || is_wfa_rule;
                const d = Math.round(map.distance([lat, lng], office));

                component.set('hasLocation', true);
                component.set('isWithinRadius', okBase);

                if (distBadge && distText) {
                    distBadge.classList.remove('hidden');
                    distBadge.classList.add('inline-flex');

                    if (ok) {
                        distBadge.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200';
                        distText.textContent = `Jarak ke kantor: ${d} m (Di dalam radius)`;
                    } else {
                        distBadge.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 border border-rose-200';
                        distText.textContent = `Jarak ke kantor: ${d} m (Di luar batas ${radius}m)`;
                    }
                }

                if (ok) {
                    component.set('latitude', lat);
                    component.set('longitude', lng);
                    component.set('uiWarning', null);
                } else {
                    component.set('uiWarning', 'Lokasi Anda berada di luar radius kantor (' + d + ' meter, melebihi batas ' + radius + ' meter). Silakan mendekat ke area kantor.');
                }

                if (btnText) btnText.textContent = 'Perbarui Lokasi GPS';
                if (btnTag) btnTag.disabled = false;
            }, (error) => {
                let msg = 'Gagal mengambil lokasi GPS.';
                if (error.code === 1) msg = 'Izin lokasi (GPS) ditolak browser. Mohon izinkan akses lokasi pada browser Anda.';
                if (error.code === 2) msg = 'Sinyal GPS tidak tersedia. Coba aktifkan GPS atau pindah ke tempat terbuka.';
                if (error.code === 3) msg = 'Waktu permintaan lokasi habis (timeout). Silakan coba klik kembali.';
                
                component.set('uiWarning', msg);
                component.set('hasLocation', false);

                if (btnText) btnText.textContent = 'Ambil Lokasi Saya (GPS)';
                if (btnTag) btnTag.disabled = false;
            }, {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            });
        }

        function isWithinRadiusBase(lat, lng, center, radius) {
            const distance = map.distance([lat, lng], center);
            return distance <= radius;
        }
    </script>
</div>
