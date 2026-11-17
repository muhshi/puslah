{{-- JANGAN panggil @filamentScripts di sini. Panel sudah manggil itu. --}}
<script src="{{ asset('js/filament/forms/components/file-upload.js') }}" data-navigate-track></script>
<script src="{{ asset('js/filament/forms/components/date-time-picker.js') }}" data-navigate-track></script>
<script src="{{ asset('js/filament/forms/components/textarea.js') }}" data-navigate-track></script>
<script src="{{ asset('js/filament/widgets/components/chart.js') }}" data-navigate-track></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
    if (typeof window.mapWithSearchPicker === 'undefined') {
        window.mapWithSearchPicker = function (config) {
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
