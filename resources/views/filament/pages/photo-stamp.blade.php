<x-filament-panels::page>
    {{-- Leaflet CSS & JS --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        .stamp-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        @media (max-width: 768px) {
            .stamp-container {
                grid-template-columns: 1fr;
            }
        }

        .stamp-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .stamp-form label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--fi-body-text-color, #374151);
            margin-bottom: 0.25rem;
            display: block;
        }

        .stamp-form input[type="text"],
        .stamp-form input[type="number"],
        .stamp-form select {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid rgb(209 213 219);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            background: white;
            color: #111827;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .dark .stamp-form input[type="text"],
        .dark .stamp-form input[type="number"],
        .dark .stamp-form select {
            background: rgb(55 65 81);
            border-color: rgb(75 85 99);
            color: #f9fafb;
        }

        .stamp-form input:focus,
        .stamp-form select:focus {
            outline: none;
            border-color: rgb(99 102 241);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
        }

        .stamp-field-group {
            display: flex;
            flex-direction: column;
        }

        .stamp-upload-zone {
            border: 2px dashed rgb(209 213 219);
            border-radius: 0.75rem;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            background: rgb(249 250 251);
        }

        .dark .stamp-upload-zone {
            border-color: rgb(75 85 99);
            background: rgb(31 41 55);
        }

        .stamp-upload-zone:hover,
        .stamp-upload-zone.dragover {
            border-color: rgb(99 102 241);
            background: rgb(238 242 255);
        }

        .dark .stamp-upload-zone:hover,
        .dark .stamp-upload-zone.dragover {
            background: rgb(49 46 129 / 0.2);
        }

        .stamp-upload-zone svg {
            width: 3rem;
            height: 3rem;
            margin: 0 auto 0.75rem;
            color: rgb(156 163 175);
        }

        .stamp-upload-zone p {
            color: rgb(107 114 128);
            font-size: 0.875rem;
        }

        .stamp-upload-zone .browse-link {
            color: rgb(99 102 241);
            font-weight: 600;
            cursor: pointer;
        }

        .stamp-preview-area {
            position: relative;
            background: rgb(17 24 39);
            border-radius: 0.75rem;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 400px;
        }

        .stamp-preview-area canvas {
            max-width: 100%;
            max-height: 70vh;
            display: block;
        }

        .stamp-preview-placeholder {
            text-align: center;
            color: rgb(156 163 175);
            padding: 2rem;
        }

        .stamp-preview-placeholder svg {
            width: 4rem;
            height: 4rem;
            margin: 0 auto 1rem;
            opacity: 0.5;
        }

        .stamp-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .stamp-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            border: none;
        }

        .stamp-btn-primary {
            background: rgb(99 102 241);
            color: white;
        }

        .stamp-btn-primary:hover {
            background: rgb(79 70 229);
        }

        .stamp-btn-primary:disabled {
            background: rgb(165 180 252);
            cursor: not-allowed;
        }

        .stamp-btn-danger {
            background: rgb(254 226 226);
            color: rgb(185 28 28);
        }

        .stamp-btn-danger:hover {
            background: rgb(254 202 202);
        }

        .stamp-settings-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: end;
        }

        .stamp-settings-row .stamp-field-group {
            flex: 1;
            min-width: 120px;
        }

        .stamp-color-input {
            width: 100%;
            height: 38px;
            border: 1px solid rgb(209 213 219);
            border-radius: 0.5rem;
            cursor: pointer;
            padding: 2px;
        }

        .dark .stamp-form label {
            color: rgb(229 231 235);
        }

        .stamp-gps-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid rgb(209 213 219);
            background: white;
            color: rgb(55 65 81);
            transition: all 0.15s;
            white-space: nowrap;
        }

        .dark .stamp-gps-btn {
            background: rgb(55 65 81);
            border-color: rgb(75 85 99);
            color: rgb(229 231 235);
        }

        .stamp-gps-btn:hover {
            background: rgb(243 244 246);
            border-color: rgb(99 102 241);
        }

        .stamp-coord-row {
            display: flex;
            gap: 0.5rem;
            align-items: end;
        }

        .stamp-coord-row .stamp-field-group {
            flex: 1;
        }

        .stamp-file-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: rgb(243 244 246);
            border-radius: 0.5rem;
            font-size: 0.8rem;
            color: rgb(55 65 81);
        }

        .dark .stamp-file-info {
            background: rgb(55 65 81);
            color: rgb(229 231 235);
        }

        .stamp-file-info .file-name {
            font-weight: 600;
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Range Slider */
        .stamp-range-wrap {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stamp-range-wrap input[type="range"] {
            flex: 1;
            height: 6px;
            -webkit-appearance: none;
            appearance: none;
            background: rgb(209 213 219);
            border-radius: 3px;
            outline: none;
        }

        .stamp-range-wrap input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: rgb(99 102 241);
            cursor: pointer;
        }

        .stamp-range-wrap .range-value {
            min-width: 36px;
            text-align: center;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--fi-body-text-color, #374151);
        }

        /* Map Modal */
        .map-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .map-modal-overlay.active {
            display: flex;
        }

        .map-modal {
            background: white;
            border-radius: 0.75rem;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
        }

        .dark .map-modal {
            background: rgb(31 41 55);
        }

        .map-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgb(229 231 235);
        }

        .dark .map-modal-header {
            border-bottom-color: rgb(75 85 99);
        }

        .map-modal-header h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--fi-body-text-color, #111827);
            margin: 0;
        }

        .map-modal-header button {
            background: none;
            border: none;
            cursor: pointer;
            color: rgb(107 114 128);
            font-size: 1.25rem;
            padding: 0.25rem;
        }

        #mapContainer {
            height: 400px;
            width: 100%;
        }

        .map-modal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1.25rem;
            border-top: 1px solid rgb(229 231 235);
            gap: 0.75rem;
        }

        .dark .map-modal-footer {
            border-top-color: rgb(75 85 99);
        }

        .map-modal-coords {
            font-size: 0.8rem;
            color: rgb(107 114 128);
            flex: 1;
        }

        .dark .map-modal-coords {
            color: rgb(156 163 175);
        }

        /* Map Search */
        .map-search-wrap {
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid rgb(229 231 235);
        }

        .dark .map-search-wrap {
            border-bottom-color: rgb(75 85 99);
        }

        .map-search-wrap form {
            display: flex;
            gap: 0.5rem;
        }

        .map-search-wrap input {
            flex: 1;
            padding: 0.5rem 0.75rem;
            border: 1px solid rgb(209 213 219);
            border-radius: 0.5rem;
            font-size: 0.85rem;
            background: white;
            color: #111827;
        }

        .dark .map-search-wrap input {
            background: rgb(55 65 81);
            border-color: rgb(75 85 99);
            color: #f9fafb;
        }

        .map-search-wrap input:focus {
            outline: none;
            border-color: rgb(99 102 241);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
        }

        .map-search-wrap button {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            background: rgb(99 102 241);
            color: white;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }

        .map-search-wrap button:hover {
            background: rgb(79 70 229);
        }

        .map-search-wrap button:disabled {
            background: rgb(165 180 252);
            cursor: not-allowed;
        }

        .map-search-results {
            list-style: none;
            margin: 0.5rem 0 0;
            padding: 0;
            max-height: 120px;
            overflow-y: auto;
            font-size: 0.8rem;
        }

        .map-search-results li {
            padding: 0.4rem 0.5rem;
            cursor: pointer;
            border-radius: 0.25rem;
            color: rgb(55 65 81);
        }

        .dark .map-search-results li {
            color: rgb(229 231 235);
        }

        .map-search-results li:hover {
            background: rgb(238 242 255);
        }

        .dark .map-search-results li:hover {
            background: rgb(49 46 129 / 0.3);
        }
    </style>

    <div class="stamp-container">
        {{-- Left: Form --}}
        <div class="stamp-form">
            {{-- Upload Zone --}}
            <div id="uploadZone" class="stamp-upload-zone" onclick="document.getElementById('fileInput').click()">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                </svg>
                <p>Drag & drop foto di sini, atau <span class="browse-link">browse</span></p>
                <p style="font-size: 0.75rem; margin-top: 0.5rem; color: rgb(156 163 175);">JPG, PNG — Maks 25MB</p>
                <input type="file" id="fileInput" accept="image/*" hidden>
            </div>

            {{-- File info --}}
            <div id="fileInfo" style="display: none;" class="stamp-file-info">
                <span class="file-name" id="fileName"></span>
                <button class="stamp-btn stamp-btn-danger" onclick="clearPhoto()"
                    style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">Hapus</button>
            </div>

            {{-- Judul Kegiatan --}}
            <div class="stamp-field-group">
                <label for="judul">Judul Kegiatan</label>
                <input type="text" id="judul" placeholder="Contoh: Survei Lapangan" oninput="renderPreview()">
            </div>

            {{-- Koordinat --}}
            <div class="stamp-field-group">
                <label>Koordinat</label>
                <div class="stamp-coord-row">
                    <div class="stamp-field-group">
                        <input type="text" id="latitude" placeholder="Latitude" oninput="renderPreview()">
                    </div>
                    <div class="stamp-field-group">
                        <input type="text" id="longitude" placeholder="Longitude" oninput="renderPreview()">
                    </div>
                    <div class="stamp-field-group">
                        <input type="text" id="elevasi" placeholder="Elevasi (m)" oninput="renderPreview()">
                    </div>
                    <button type="button" class="stamp-gps-btn" onclick="getGPS()" id="gpsBtn"
                        title="Ambil lokasi GPS saat ini">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor" style="width:16px;height:16px;">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                        </svg>
                        GPS
                    </button>
                    <button type="button" class="stamp-gps-btn" onclick="openMapPicker()"
                        title="Pilih lokasi dari peta">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor" style="width:16px;height:16px;">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
                        </svg>
                        Peta
                    </button>
                </div>
            </div>

            {{-- Tanggal & Waktu --}}
            <div class="stamp-field-group">
                <label for="tanggalWaktu">Tanggal & Waktu</label>
                <div style="display:flex; gap:0.5rem; align-items:end;">
                    <input type="datetime-local" step="1" id="tanggalWaktu" oninput="renderPreview()" style="flex:1;">
                    <button type="button" class="stamp-gps-btn" onclick="setCurrentTime()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor" style="width:16px;height:16px;">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Sekarang
                    </button>
                </div>
            </div>

            {{-- Pengaturan Stempel --}}
            <details style="margin-top: 0.5rem;">
                <summary
                    style="cursor:pointer; font-weight:600; font-size:0.875rem; color: var(--fi-body-text-color, #374151);">
                    ⚙️ Pengaturan Stempel</summary>
                <div style="margin-top: 0.75rem;" class="stamp-form">
                    <div class="stamp-settings-row">
                        <div class="stamp-field-group">
                            <label for="fontSize">Ukuran Font</label>
                            <select id="fontSize" onchange="renderPreview()">
                                <option value="auto">Otomatis</option>
                                <option value="12">12px</option>
                                <option value="14">14px</option>
                                <option value="16">16px</option>
                                <option value="18">18px</option>
                                <option value="20">20px</option>
                                <option value="24">24px</option>
                                <option value="28">28px</option>
                                <option value="32">32px</option>
                                <option value="36">36px</option>
                                <option value="40">40px</option>
                                <option value="48">48px</option>
                            </select>
                        </div>
                        <div class="stamp-field-group">
                            <label for="fontColor">Warna Teks</label>
                            <input type="color" id="fontColor" value="#ffffff" class="stamp-color-input"
                                onchange="renderPreview()">
                        </div>
                    </div>
                    <div class="stamp-field-group" style="margin-top: 0.5rem;">
                        <label for="bgOpacity">Background Stempel: <span id="bgOpacityValue">0%</span></label>
                        <div class="stamp-range-wrap">
                            <span style="font-size:0.75rem; color:rgb(107 114 128);">0%</span>
                            <input type="range" id="bgOpacity" min="0" max="100" value="0"
                                oninput="updateBgLabel(); renderPreview();">
                            <span style="font-size:0.75rem; color:rgb(107 114 128);">100%</span>
                        </div>
                    </div>
                </div>
            </details>

            {{-- Actions --}}
            <div class="stamp-actions" style="margin-top: 0.5rem;">
                <button class="stamp-btn stamp-btn-primary" id="downloadBtn" onclick="downloadPhoto()" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                        stroke="currentColor" style="width:18px;height:18px;">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12M12 16.5V3" />
                    </svg>
                    Download Foto
                </button>
            </div>
        </div>

        {{-- Right: Preview --}}
        <div>
            <div class="stamp-preview-area" id="previewArea">
                <div class="stamp-preview-placeholder" id="previewPlaceholder">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0023.25 18.75V5.25A2.25 2.25 0 0021 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z" />
                    </svg>
                    <p>Upload foto untuk melihat preview</p>
                </div>
                <canvas id="stampCanvas" style="display:none;"></canvas>
            </div>
        </div>
    </div>

    {{-- Map Picker Modal --}}
    <div class="map-modal-overlay" id="mapModal">
        <div class="map-modal">
            <div class="map-modal-header">
                <h3>📍 Pilih Lokasi dari Peta</h3>
                <button onclick="closeMapPicker()">&times;</button>
            </div>
            <div class="map-search-wrap">
                <form onsubmit="searchLocation(event)">
                    <input type="text" id="mapSearchInput" placeholder="Cari lokasi... contoh: Monas, Jakarta">
                    <button type="submit" id="mapSearchBtn">Cari</button>
                </form>
                <ul class="map-search-results" id="mapSearchResults"></ul>
            </div>
            <div id="mapContainer"></div>
            <div class="map-modal-footer">
                <div class="map-modal-coords" id="mapCoords">Klik pada peta untuk memilih lokasi</div>
                <button class="stamp-btn stamp-btn-primary" onclick="confirmMapLocation()" id="mapConfirmBtn" disabled
                    style="padding: 0.5rem 1rem; font-size: 0.8rem;">
                    Pilih Lokasi Ini
                </button>
            </div>
        </div>
    </div>

    {{-- Hidden iframe for download (bypasses Livewire navigation) --}}
    <iframe id="downloadFrame" style="display:none;"></iframe>

    <script>
        let uploadedImage = null;
        let originalFileName = 'foto';
        const canvas = document.getElementById('stampCanvas');
        const ctx = canvas.getContext('2d');

        // Map variables
        let map = null;
        let mapMarker = null;
        let selectedMapLat = null;
        let selectedMapLng = null;

        // =============== FILE UPLOAD ===============
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');

        ['dragenter', 'dragover'].forEach(evt => {
            uploadZone.addEventListener(evt, e => {
                e.preventDefault();
                uploadZone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(evt => {
            uploadZone.addEventListener(evt, e => {
                e.preventDefault();
                uploadZone.classList.remove('dragover');
            });
        });

        uploadZone.addEventListener('drop', e => {
            const files = e.dataTransfer.files;
            if (files.length > 0) handleFile(files[0]);
        });

        fileInput.addEventListener('change', e => {
            if (e.target.files.length > 0) handleFile(e.target.files[0]);
        });

        function handleFile(file) {
            if (!file.type.startsWith('image/')) {
                alert('Hanya file gambar yang didukung.');
                return;
            }
            if (file.size > 25 * 1024 * 1024) {
                alert('Ukuran file maksimal 25MB.');
                return;
            }

            // Store original filename for download
            originalFileName = file.name.replace(/\.[^/.]+$/, '') || 'foto';

            document.getElementById('fileName').textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(1) + 'MB)';
            document.getElementById('fileInfo').style.display = 'flex';
            uploadZone.style.display = 'none';

            const reader = new FileReader();
            reader.onload = e => {
                const img = new Image();
                img.onload = () => {
                    uploadedImage = img;
                    document.getElementById('downloadBtn').disabled = false;
                    renderPreview();
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        function clearPhoto() {
            uploadedImage = null;
            originalFileName = 'foto';
            fileInput.value = '';
            document.getElementById('fileInfo').style.display = 'none';
            uploadZone.style.display = '';
            document.getElementById('downloadBtn').disabled = true;

            canvas.style.display = 'none';
            document.getElementById('previewPlaceholder').style.display = '';
        }

        // =============== GPS ===============
        function getGPS() {
            const btn = document.getElementById('gpsBtn');
            btn.textContent = '...';
            btn.disabled = true;

            if (!navigator.geolocation) {
                alert('GPS tidak didukung browser ini.');
                resetGpsBtn();
                return;
            }

            navigator.geolocation.getCurrentPosition(
                pos => {
                    document.getElementById('latitude').value = pos.coords.latitude.toFixed(6);
                    document.getElementById('longitude').value = pos.coords.longitude.toFixed(6);
                    if (pos.coords.altitude !== null) {
                        document.getElementById('elevasi').value = pos.coords.altitude.toFixed(1);
                    }
                    resetGpsBtn();
                    renderPreview();
                },
                err => {
                    alert('Gagal mendapatkan lokasi: ' + err.message);
                    resetGpsBtn();
                },
                { enableHighAccuracy: true }
            );
        }

        function resetGpsBtn() {
            const btn = document.getElementById('gpsBtn');
            btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg> GPS';
            btn.disabled = false;
        }

        // =============== MAP PICKER ===============
        function openMapPicker() {
            const modal = document.getElementById('mapModal');
            modal.classList.add('active');

            // Clear previous search
            document.getElementById('mapSearchInput').value = '';
            document.getElementById('mapSearchResults').innerHTML = '';

            // Default center: use existing lat/lng or Indonesia center
            let initLat = parseFloat(document.getElementById('latitude').value) || -6.2088;
            let initLng = parseFloat(document.getElementById('longitude').value) || 106.8456;

            if (!map) {
                map = L.map('mapContainer').setView([initLat, initLng], 13);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 19
                }).addTo(map);

                map.on('click', function (e) {
                    placeMapMarker(e.latlng.lat, e.latlng.lng);
                });
            } else {
                map.setView([initLat, initLng], 13);
            }

            // If already have coords, show marker
            if (document.getElementById('latitude').value && document.getElementById('longitude').value) {
                placeMapMarker(initLat, initLng);
            }

            // Fix Leaflet rendering in modal
            setTimeout(() => map.invalidateSize(), 200);
        }

        function placeMapMarker(lat, lng) {
            selectedMapLat = lat;
            selectedMapLng = lng;
            const latlng = L.latLng(lat, lng);

            if (mapMarker) {
                mapMarker.setLatLng(latlng);
            } else {
                mapMarker = L.marker(latlng, { draggable: true }).addTo(map);
                mapMarker.on('dragend', function (ev) {
                    const pos = ev.target.getLatLng();
                    selectedMapLat = pos.lat;
                    selectedMapLng = pos.lng;
                    updateMapCoordsDisplay();
                });
            }
            updateMapCoordsDisplay();
            document.getElementById('mapConfirmBtn').disabled = false;
        }

        function updateMapCoordsDisplay() {
            document.getElementById('mapCoords').textContent =
                `📍 ${selectedMapLat.toFixed(6)}, ${selectedMapLng.toFixed(6)}`;
        }

        // =============== MAP SEARCH (Nominatim) ===============
        function searchLocation(e) {
            e.preventDefault();
            const query = document.getElementById('mapSearchInput').value.trim();
            if (!query) return;

            const btn = document.getElementById('mapSearchBtn');
            const resultsList = document.getElementById('mapSearchResults');
            btn.disabled = true;
            btn.textContent = '...';
            resultsList.innerHTML = '';

            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&countrycodes=id`)
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btn.textContent = 'Cari';

                    if (data.length === 0) {
                        resultsList.innerHTML = '<li style="color:rgb(156 163 175);cursor:default;">Tidak ditemukan</li>';
                        return;
                    }

                    data.forEach(item => {
                        const li = document.createElement('li');
                        li.textContent = item.display_name;
                        li.onclick = () => {
                            const lat = parseFloat(item.lat);
                            const lng = parseFloat(item.lon);
                            map.setView([lat, lng], 16);
                            placeMapMarker(lat, lng);
                            resultsList.innerHTML = '';
                            document.getElementById('mapSearchInput').value = item.display_name.split(',')[0];
                        };
                        resultsList.appendChild(li);
                    });
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.textContent = 'Cari';
                    resultsList.innerHTML = '<li style="color:rgb(220 38 38);cursor:default;">Gagal mencari: ' + err.message + '</li>';
                });
        }

        function closeMapPicker() {
            document.getElementById('mapModal').classList.remove('active');
        }

        function confirmMapLocation() {
            if (selectedMapLat !== null && selectedMapLng !== null) {
                document.getElementById('latitude').value = selectedMapLat.toFixed(6);
                document.getElementById('longitude').value = selectedMapLng.toFixed(6);
                renderPreview();
            }
            closeMapPicker();
        }

        // Close modal on overlay click
        document.getElementById('mapModal').addEventListener('click', function (e) {
            if (e.target === this) closeMapPicker();
        });

        // =============== TIME ===============
        function setCurrentTime() {
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('tanggalWaktu').value = now.toISOString().slice(0, 19);
            renderPreview();
        }

        // =============== BG OPACITY LABEL ===============
        function updateBgLabel() {
            document.getElementById('bgOpacityValue').textContent = document.getElementById('bgOpacity').value + '%';
        }

        // =============== RENDER PREVIEW ===============
        function renderPreview() {
            if (!uploadedImage) return;

            const img = uploadedImage;
            canvas.width = img.naturalWidth;
            canvas.height = img.naturalHeight;

            // Draw original image
            ctx.drawImage(img, 0, 0);

            // Collect stamp lines
            const lines = [];
            const judul = document.getElementById('judul').value.trim();
            const lat = document.getElementById('latitude').value.trim();
            const lon = document.getElementById('longitude').value.trim();
            const elev = document.getElementById('elevasi').value.trim();
            let waktu = document.getElementById('tanggalWaktu').value.trim();

            if (judul) lines.push(judul);

            // Build coordinate line
            let coordParts = [];
            if (lat) coordParts.push(lat);
            if (lon) coordParts.push(lon);
            if (elev) coordParts.push(elev + 'm');
            if (coordParts.length > 0) lines.push(coordParts.join(', '));

            if (waktu) {
                const d = new Date(waktu);
                if (!isNaN(d.getTime())) {
                    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    waktu = `${months[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}:${String(d.getSeconds()).padStart(2, '0')}`;
                }
                lines.push(waktu);
            }

            if (lines.length === 0) {
                canvas.style.display = 'block';
                document.getElementById('previewPlaceholder').style.display = 'none';
                return;
            }

            // Calculate font size
            const fontSizeSetting = document.getElementById('fontSize').value;
            let fontSize;
            if (fontSizeSetting === 'auto') {
                fontSize = Math.max(14, Math.min(48, Math.round(canvas.width * 0.02)));
            } else {
                fontSize = parseInt(fontSizeSetting);
            }

            const fontColor = document.getElementById('fontColor').value;
            const bgOpacity = parseInt(document.getElementById('bgOpacity').value) / 100;

            const lineHeight = fontSize * 1.4;
            const padding = fontSize * 0.8;
            const margin = fontSize * 0.6;

            ctx.font = `bold ${fontSize}px "Segoe UI", "Helvetica Neue", Arial, sans-serif`;
            ctx.textAlign = 'right';
            ctx.textBaseline = 'top';

            // Measure text widths
            let maxWidth = 0;
            lines.forEach(line => {
                const w = ctx.measureText(line).width;
                if (w > maxWidth) maxWidth = w;
            });

            const blockWidth = maxWidth + padding * 2;
            const blockHeight = lines.length * lineHeight + padding * 2 - (lineHeight - fontSize) * 0.5;

            // Position: bottom-right
            const blockX = canvas.width - margin - blockWidth;
            const blockY = canvas.height - margin - blockHeight;

            // Draw background (only if opacity > 0)
            if (bgOpacity > 0) {
                ctx.fillStyle = `rgba(0, 0, 0, ${bgOpacity})`;
                const radius = fontSize * 0.4;
                ctx.beginPath();
                ctx.moveTo(blockX + radius, blockY);
                ctx.lineTo(blockX + blockWidth - radius, blockY);
                ctx.arcTo(blockX + blockWidth, blockY, blockX + blockWidth, blockY + radius, radius);
                ctx.lineTo(blockX + blockWidth, blockY + blockHeight - radius);
                ctx.arcTo(blockX + blockWidth, blockY + blockHeight, blockX + blockWidth - radius, blockY + blockHeight, radius);
                ctx.lineTo(blockX + radius, blockY + blockHeight);
                ctx.arcTo(blockX, blockY + blockHeight, blockX, blockY + blockHeight - radius, radius);
                ctx.lineTo(blockX, blockY + radius);
                ctx.arcTo(blockX, blockY, blockX + radius, blockY, radius);
                ctx.closePath();
                ctx.fill();
            }

            // Draw text — right-aligned
            ctx.fillStyle = fontColor;
            ctx.font = `bold ${fontSize}px "Segoe UI", "Helvetica Neue", Arial, sans-serif`;
            ctx.textAlign = 'right';
            ctx.textBaseline = 'top';

            const textX = blockX + blockWidth - padding;
            let textY = blockY + padding;

            // Text shadow for readability
            ctx.shadowColor = 'rgba(0,0,0,0.6)';
            ctx.shadowBlur = 4;
            ctx.shadowOffsetX = 1;
            ctx.shadowOffsetY = 1;

            lines.forEach(line => {
                ctx.fillText(line, textX, textY);
                textY += lineHeight;
            });

            // Reset shadow
            ctx.shadowColor = 'transparent';
            ctx.shadowBlur = 0;
            ctx.shadowOffsetX = 0;
            ctx.shadowOffsetY = 0;

            canvas.style.display = 'block';
            document.getElementById('previewPlaceholder').style.display = 'none';
        }

        // =============== DOWNLOAD ===============
        function downloadPhoto() {
            if (!uploadedImage) return;

            renderPreview(); // Ensure latest stamp

            // Build filename
            const judul = document.getElementById('judul').value.trim();
            let safeName;
            if (judul) {
                safeName = judul.replace(/[^a-zA-Z0-9\s_-]/g, '').replace(/\s+/g, '_').substring(0, 50);
            } else {
                safeName = originalFileName.replace(/[^a-zA-Z0-9\s_\-.]/g, '').replace(/\s+/g, '_').substring(0, 50) || 'foto';
            }
            const fileName = safeName + '_stamped.jpg';

            // Convert canvas to blob, then trigger download
            // Must bypass Livewire's wire:navigate SPA interception
            canvas.toBlob(function (blob) {
                const url = URL.createObjectURL(blob);

                const a = document.createElement('a');
                a.href = url;
                a.download = fileName;

                // These attributes tell Livewire 3 to NOT intercept this link
                a.setAttribute('data-navigate-ignore', '');
                a.setAttribute('wire:navigate.prevent', '');
                a.setAttribute('target', '_self');

                a.style.display = 'none';
                document.body.appendChild(a);

                // Dispatch a native click that bypasses Livewire's event delegation
                const clickEvent = new MouseEvent('click', {
                    bubbles: false,
                    cancelable: false,
                    view: window
                });
                a.dispatchEvent(clickEvent);

                setTimeout(function () {
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                }, 1000);
            }, 'image/jpeg', 0.95);
        }

        // =============== INIT ===============
        setCurrentTime();
    </script>
</x-filament-panels::page>