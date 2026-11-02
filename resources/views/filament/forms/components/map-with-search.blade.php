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
            statePath: '{{ $getStatePath() }}',
            initialLat: {{ (float) (data_get($getState(), 'lat') ?: -6.894561) }},
            initialLng: {{ (float) (data_get($getState(), 'lng') ?: 110.637492) }},
        })"
        x-init="initComponent()"
        class="flex flex-col gap-3 w-full"
    >
        {{-- Search & GPS Bar --}}
        <div class="flex flex-col sm:flex-row gap-2 w-full">
            <div class="relative flex-1">
                <div class="flex gap-1.5">
                    <input
                        type="text"
                        x-model="query"
                        @keydown.enter.prevent="searchLocation()"
                        placeholder="Cari wilayah/desa/kecamatan... (contoh: Bonang, Morodemak, Alun-alun Demak)"
                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                    />
                    <button
                        type="button"
                        @click="searchLocation()"
                        :disabled="searching"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white bg-primary-600 hover:bg-primary-500 disabled:opacity-60 rounded-lg shadow-sm transition"
                    >
                        <span x-show="!searching">Cari</span>
                        <span x-show="searching" class="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span>
                    </button>
                </div>

                {{-- Dropdown Hasil Pencarian --}}
                <div
                    x-show="results.length > 0 || searchError"
                    @click.outside="results = []; searchError = null"
                    x-transition
                    class="absolute z-50 left-0 right-0 mt-1 max-h-56 overflow-y-auto bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl"
                    style="display: none;"
                >
                    <template x-if="searchError">
                        <div class="p-3 text-xs text-danger-600 dark:text-danger-400" x-text="searchError"></div>
                    </template>

                    <template x-for="(item, index) in results" :key="index">
                        <div
                            @click="selectResult(item)"
                            class="p-2.5 text-xs text-gray-700 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-primary-950/40 cursor-pointer border-b border-gray-100 dark:border-gray-700/60 last:border-b-0 flex items-start gap-2 transition"
                        >
                            <span class="text-primary-500 mt-0.5">📍</span>
                            <div class="flex-1">
                                <span class="font-medium" x-text="item.display_name"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <button
                type="button"
                @click="useCurrentGps()"
                class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 text-xs font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm transition shrink-0"
                title="Gunakan posisi GPS perangkat saat ini"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>GPS Saya</span>
            </button>
        </div>

        {{-- Map Container --}}
        <div class="relative w-full rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 shadow-inner" style="height: 380px;">
            <div x-ref="mapContainer" class="w-full h-full z-0"></div>
        </div>

        {{-- Selected Coordinates Bar --}}
        <div class="flex items-center justify-between px-3 py-2 bg-gray-50 dark:bg-gray-800/60 rounded-lg border border-gray-200 dark:border-gray-700/80 text-xs">
            <div class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Titik Terpilih:</span>
                <span class="font-mono font-bold text-gray-900 dark:text-white" x-text="currentLat ? currentLat + ', ' + currentLng : 'Belum dipilih'"></span>
            </div>
            <span class="text-gray-400 dark:text-gray-500 italic hidden sm:inline">Klik peta atau seret marker merah untuk ubah titik</span>
        </div>
    </div>

    {{-- Script Handler --}}
    @once
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            function mapWithSearchPicker(config) {
                return {
                    statePath: config.statePath,
                    query: '',
                    searching: false,
                    results: [],
                    searchError: null,
                    map: null,
                    marker: null,
                    currentLat: config.initialLat,
                    currentLng: config.initialLng,

                    initComponent() {
                        this.updateLivewireState(this.currentLat, this.currentLng);
                        this.$nextTick(() => {
                            this.ensureLeafletAndInit();
                        });
                    },

                    ensureLeafletAndInit() {
                        if (typeof L !== 'undefined') {
                            this.initMap();
                        } else {
                            const timer = setInterval(() => {
                                if (typeof L !== 'undefined') {
                                    clearInterval(timer);
                                    this.initMap();
                                }
                            }, 50);
                        }
                    },

                    initMap() {
                        const container = this.$refs.mapContainer;
                        if (!container) return;

                        if (this.map) {
                            this.map.remove();
                        }

                        this.map = L.map(container).setView([this.currentLat, this.currentLng], 15);

                        const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; OpenStreetMap'
                        });

                        const googleHybrid = L.tileLayer('https://mt0.google.com/vt/lyrs=y&hl=en&x={x}&y={y}&z={z}&s=Ga', {
                            maxZoom: 20,
                            attribution: '&copy; Google'
                        });

                        googleHybrid.addTo(this.map);

                        L.control.layers({
                            "Satelit (Hybrid)": googleHybrid,
                            "Peta Jalan (OSM)": osmLayer
                        }, null, { position: 'topright' }).addTo(this.map);

                        this.placeMarker(this.currentLat, this.currentLng);

                        this.map.on('click', (e) => {
                            this.placeMarker(e.latlng.lat, e.latlng.lng);
                        });

                        // Re-render size after modal animation
                        setTimeout(() => {
                            if (this.map) this.map.invalidateSize();
                        }, 250);
                    },

                    placeMarker(lat, lng) {
                        this.currentLat = parseFloat(lat.toFixed(6));
                        this.currentLng = parseFloat(lng.toFixed(6));
                        this.updateLivewireState(this.currentLat, this.currentLng);

                        const latlng = L.latLng(this.currentLat, this.currentLng);

                        if (this.marker) {
                            this.marker.setLatLng(latlng);
                        } else {
                            this.marker = L.marker(latlng, { draggable: true }).addTo(this.map);
                            this.marker.on('dragend', (e) => {
                                const pos = e.target.getLatLng();
                                this.currentLat = parseFloat(pos.lat.toFixed(6));
                                this.currentLng = parseFloat(pos.lng.toFixed(6));
                                this.updateLivewireState(this.currentLat, this.currentLng);
                            });
                        }
                    },

                    updateLivewireState(lat, lng) {
                        if (this.statePath) {
                            this.$wire.set(this.statePath, {
                                lat: lat,
                                lng: lng
                            });
                        }
                    },

                    searchLocation() {
                        const q = this.query.trim();
                        if (!q) return;

                        this.searching = true;
                        this.searchError = null;
                        this.results = [];

                        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=6&countrycodes=id`)
                            .then(res => res.json())
                            .then(data => {
                                this.searching = false;
                                this.results = data || [];
                                if (this.results.length === 0) {
                                    this.searchError = 'Wilayah tidak ditemukan. Coba gunakan nama desa/kecamatan yang lebih spesifik.';
                                }
                            })
                            .catch(err => {
                                this.searching = false;
                                this.searchError = 'Gagal mencari lokasi: ' + err.message;
                            });
                    },

                    selectResult(item) {
                        const lat = parseFloat(item.lat);
                        const lng = parseFloat(item.lon);
                        if (this.map) {
                            this.map.setView([lat, lng], 16);
                        }
                        this.placeMarker(lat, lng);
                        this.results = [];
                        this.searchError = null;
                        this.query = item.display_name.split(',')[0];
                    },

                    useCurrentGps() {
                        if (!navigator.geolocation) {
                            alert('Browser Anda tidak mendukung deteksi GPS.');
                            return;
                        }

                        navigator.geolocation.getCurrentPosition(
                            (pos) => {
                                const lat = pos.coords.latitude;
                                const lng = pos.coords.longitude;
                                if (this.map) {
                                    this.map.setView([lat, lng], 17);
                                }
                                this.placeMarker(lat, lng);
                            },
                            (err) => {
                                alert('Gagal membaca GPS: ' + err.message);
                            },
                            { enableHighAccuracy: true, timeout: 10000 }
                        );
                    }
                };
            }
        </script>
    @endonce
</x-filament-forms::field-wrapper>
