<x-filament-forms::field-wrapper
    :id="$getId()"
    :label="$getLabel()"
    :label-sr-only="$isLabelHidden()"
    :helper-text="$getHelperText()"
    :hint="$getHint()"
    :required="$isRequired()"
    :state-path="$getStatePath()"
>
    <div
        x-data="mapWithSearchPicker({
            statePath: @js($getStatePath()),
            initialLat: {{ (float) (data_get($getState(), 'lat') ?: -6.894561) }},
            initialLng: {{ (float) (data_get($getState(), 'lng') ?: 110.637492) }},
        })"
        x-init="init()"
        x-on:destroy="destroy()"
        class="flex flex-col gap-3 w-full"
    >
        {{-- Search & GPS Bar --}}
        <div class="flex flex-col sm:flex-row gap-2 w-full">
            <div class="relative flex-1">
                <div class="flex gap-2">
                    <div class="flex-1">
                        <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                            <x-filament::input
                                type="text"
                                x-model="query"
                                @keydown.enter.prevent="searchLocation()"
                                placeholder="Cari desa, kecamatan, jalan... (contoh: Bonang, Morodemak, BPS Demak)"
                            />
                        </x-filament::input.wrapper>
                    </div>

                    <x-filament::button
                        type="button"
                        color="primary"
                        icon="heroicon-m-magnifying-glass"
                        @click="searchLocation()"
                        x-bind:disabled="searching"
                    >
                        <span x-show="!searching">Cari</span>
                        <span x-show="searching" style="display: none;" class="flex items-center gap-1">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Mencari...
                        </span>
                    </x-filament::button>
                </div>

                {{-- Dropdown Hasil Pencarian --}}
                <div
                    x-show="results.length > 0 || searchError || gpsMessage"
                    @click.outside="results = []; searchError = null; gpsMessage = null"
                    x-transition
                    class="absolute left-0 right-0 mt-1.5 max-h-56 overflow-y-auto bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-2xl p-1"
                    style="display: none; z-index: 1050;"
                >
                    <template x-if="gpsMessage">
                        <div class="p-3 text-xs text-info-600 dark:text-info-400 flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span x-text="gpsMessage"></span>
                        </div>
                    </template>

                    <template x-if="searchError">
                        <div class="p-3 text-xs text-danger-600 dark:text-danger-400 flex items-center gap-2">
                            <span>⚠️</span>
                            <span x-text="searchError"></span>
                        </div>
                    </template>

                    <template x-for="(item, index) in results" :key="index">
                        <div
                            @click="selectResult(item)"
                            class="p-2.5 text-xs text-gray-800 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-primary-950/50 rounded-lg cursor-pointer flex items-start gap-2.5 transition"
                        >
                            <span class="text-primary-500 mt-0.5 text-sm shrink-0">📍</span>
                            <div class="flex-1 leading-relaxed">
                                <span class="font-semibold block text-gray-950 dark:text-white" x-text="item.display_name.split(',')[0]"></span>
                                <span class="text-[11px] text-gray-500 dark:text-gray-400 block" x-text="item.display_name"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <x-filament::button
                type="button"
                color="gray"
                icon="heroicon-m-map-pin"
                @click="useCurrentGps()"
                x-bind:disabled="gettingGps"
                class="shrink-0"
            >
                <span x-show="!gettingGps">GPS Saya</span>
                <span x-show="gettingGps" style="display: none;" class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Mencari GPS...
                </span>
            </x-filament::button>
        </div>

        {{-- Map Container --}}
        <div class="relative w-full rounded-xl overflow-hidden border border-gray-200 dark:border-gray-800 shadow-sm bg-gray-100 dark:bg-gray-900" style="height: 400px; min-height: 400px;">
            <div x-ref="mapContainer" class="w-full h-full z-0"></div>
        </div>

        {{-- Selected Coordinates Bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-3.5 py-2.5 bg-gray-50 dark:bg-white/5 rounded-xl border border-gray-200 dark:border-white/10 text-xs">
            <div class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse shrink-0"></span>
                <span class="font-medium">Koordinat Terpilih:</span>
                <span class="font-mono font-bold text-gray-950 dark:text-white" x-text="currentLat ? (currentLat + ', ' + currentLng) : 'Belum dipilih'"></span>
            </div>
            <span class="text-gray-500 dark:text-gray-400 italic text-[11px]">💡 Klik pada peta atau seret marker untuk memindahkan posisi titik</span>
        </div>
    </div>

    {{-- Fallback Script Loader if not loaded via hooks --}}
    @once
        <script>
            if (typeof window.mapWithSearchPicker === 'undefined') {
                window.mapWithSearchPicker = function(config) {
                    return {
                        query: '',
                        searching: false,
                        gettingGps: false,
                        results: [],
                        searchError: null,
                        gpsMessage: null,
                        map: null,
                        marker: null,
                        currentLat: null,
                        currentLng: null,
                        resizeObserver: null,

                        init() {
                            const initialLat = parseFloat(config.initialLat) || -6.894561;
                            const initialLng = parseFloat(config.initialLng) || 110.637492;

                            this.currentLat = parseFloat(initialLat.toFixed(6));
                            this.currentLng = parseFloat(initialLng.toFixed(6));
                            this.syncState(this.currentLat, this.currentLng);

                            this.ensureLeaflet(() => {
                                this.$nextTick(() => {
                                    this.initMap();
                                });
                            });
                        },

                        ensureLeaflet(callback) {
                            if (typeof window.L !== 'undefined') {
                                callback();
                                return;
                            }

                            if (!document.getElementById('leaflet-css')) {
                                const link = document.createElement('link');
                                link.id = 'leaflet-css';
                                link.rel = 'stylesheet';
                                link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                                link.crossOrigin = '';
                                document.head.appendChild(link);
                            }

                            if (!document.getElementById('leaflet-js')) {
                                const script = document.createElement('script');
                                script.id = 'leaflet-js';
                                script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                                script.crossOrigin = '';
                                script.onload = () => callback();
                                script.onerror = () => {
                                    this.searchError = 'Gagal memuat library peta Leaflet dari CDN.';
                                };
                                document.head.appendChild(script);
                            } else {
                                const interval = setInterval(() => {
                                    if (typeof window.L !== 'undefined') {
                                        clearInterval(interval);
                                        callback();
                                    }
                                }, 50);
                            }
                        },

                        initMap() {
                            const container = this.$refs.mapContainer;
                            if (!container || typeof window.L === 'undefined') return;

                            if (this.map) {
                                try {
                                    this.map.remove();
                                } catch (e) {}
                                this.map = null;
                            }

                            try {
                                delete L.Icon.Default.prototype._getIconUrl;
                                L.Icon.Default.mergeOptions({
                                    iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
                                    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                                    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                                });
                            } catch (e) {}

                            this.map = L.map(container, {
                                center: [this.currentLat, this.currentLng],
                                zoom: 15,
                                scrollWheelZoom: true,
                            });

                            const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                maxZoom: 19,
                                attribution: '&copy; OpenStreetMap contributors'
                            });

                            const googleHybrid = L.tileLayer('https://mt0.google.com/vt/lyrs=y&hl=id&x={x}&y={y}&z={z}&s=Ga', {
                                maxZoom: 20,
                                attribution: '&copy; Google'
                            });

                            googleHybrid.addTo(this.map);

                            L.control.layers({
                                "Satelit (Hybrid)": googleHybrid,
                                "Peta Jalan (OSM)": osmLayer
                            }, null, { position: 'topright' }).addTo(this.map);

                            this.placeMarker(this.currentLat, this.currentLng, false);

                            this.map.on('click', (e) => {
                                this.placeMarker(e.latlng.lat, e.latlng.lng, true);
                            });

                            if (window.ResizeObserver) {
                                if (this.resizeObserver) this.resizeObserver.disconnect();
                                this.resizeObserver = new ResizeObserver(() => {
                                    if (this.map) {
                                        this.map.invalidateSize();
                                    }
                                });
                                this.resizeObserver.observe(container);
                            }

                            setTimeout(() => {
                                if (this.map) this.map.invalidateSize();
                            }, 300);
                            setTimeout(() => {
                                if (this.map) this.map.invalidateSize();
                            }, 800);
                        },

                        placeMarker(lat, lng, pan = false) {
                            this.currentLat = parseFloat(Number(lat).toFixed(6));
                            this.currentLng = parseFloat(Number(lng).toFixed(6));
                            this.syncState(this.currentLat, this.currentLng);

                            if (!this.map || typeof window.L === 'undefined') return;

                            const latlng = L.latLng(this.currentLat, this.currentLng);

                            if (this.marker) {
                                this.marker.setLatLng(latlng);
                            } else {
                                this.marker = L.marker(latlng, { draggable: true }).addTo(this.map);
                                this.marker.on('dragend', (e) => {
                                    const pos = e.target.getLatLng();
                                    this.currentLat = parseFloat(pos.lat.toFixed(6));
                                    this.currentLng = parseFloat(pos.lng.toFixed(6));
                                    this.syncState(this.currentLat, this.currentLng);
                                });
                            }

                            if (pan) {
                                this.map.panTo(latlng);
                            }
                        },

                        syncState(lat, lng) {
                            const data = { lat: lat, lng: lng };
                            if (config.statePath && this.$wire) {
                                this.$wire.set(config.statePath, data);
                            }
                        },

                        searchLocation() {
                            const q = (this.query || '').trim();
                            if (!q) return;

                            this.searching = true;
                            this.searchError = null;
                            this.results = [];

                            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=6&countrycodes=id`;

                            fetch(url, {
                                headers: {
                                    'Accept': 'application/json',
                                }
                            })
                            .then(res => {
                                if (!res.ok) throw new Error('HTTP ' + res.status);
                                return res.json();
                            })
                            .then(data => {
                                this.searching = false;
                                this.results = data || [];
                                if (this.results.length === 0) {
                                    this.searchError = 'Lokasi tidak ditemukan. Coba ketik nama desa/kecamatan yang lebih spesifik.';
                                }
                            })
                            .catch(err => {
                                this.searching = false;
                                this.searchError = 'Gagal melakukan pencarian: ' + err.message;
                            });
                        },

                        selectResult(item) {
                            const lat = parseFloat(item.lat);
                            const lng = parseFloat(item.lon);

                            if (this.map) {
                                this.map.setView([lat, lng], 16);
                            }
                            this.placeMarker(lat, lng, true);
                            this.results = [];
                            this.searchError = null;
                            this.query = item.display_name.split(',')[0];
                        },

                        useCurrentGps() {
                            if (!navigator.geolocation) {
                                this.searchError = 'Browser Anda tidak mendukung deteksi GPS.';
                                return;
                            }

                            this.gettingGps = true;
                            this.searchError = null;
                            this.gpsMessage = 'Sedang mendeteksi posisi GPS...';

                            navigator.geolocation.getCurrentPosition(
                                (pos) => {
                                    this.gettingGps = false;
                                    this.gpsMessage = null;
                                    const lat = pos.coords.latitude;
                                    const lng = pos.coords.longitude;

                                    if (this.map) {
                                        this.map.setView([lat, lng], 17);
                                    }
                                    this.placeMarker(lat, lng, true);
                                },
                                (err) => {
                                    this.gettingGps = false;
                                    this.gpsMessage = null;
                                    let msg = 'Gagal membaca GPS: ';
                                    switch (err.code) {
                                        case err.PERMISSION_DENIED:
                                            msg += 'Izin akses lokasi ditolak oleh browser. Mohon izinkan akses lokasi pada browser Anda.';
                                            break;
                                        case err.POSITION_UNAVAILABLE:
                                            msg += 'Informasi lokasi tidak tersedia saat ini.';
                                            break;
                                        case err.TIMEOUT:
                                            msg += 'Waktu pencarian GPS habis (timeout). Coba lagi atau gunakan pencarian nama wilayah.';
                                            break;
                                        default:
                                            msg += err.message;
                                    }
                                    this.searchError = msg;
                                },
                                { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
                            );
                        },

                        destroy() {
                            if (this.resizeObserver) {
                                this.resizeObserver.disconnect();
                                this.resizeObserver = null;
                            }
                            if (this.map) {
                                try {
                                    this.map.remove();
                                } catch (e) {}
                                this.map = null;
                            }
                        }
                    };
                };
            }
        </script>
    @endonce
</x-filament-forms::field-wrapper>
