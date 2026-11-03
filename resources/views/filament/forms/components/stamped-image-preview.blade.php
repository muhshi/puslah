@php
    $statePath = $getStatePath();
    $lastDot = strrpos($statePath, '.');
    $parentPath = $lastDot !== false ? substr($statePath, 0, $lastDot) : $statePath;
    
    // Ambil path file saat render awal
    $rawPath = $get('file_path');
    $initialUrl = null;
    if ($rawPath) {
        if (is_string($rawPath)) {
            $initialUrl = str_starts_with($rawPath, 'http') ? $rawPath : asset('storage/' . ltrim($rawPath, '/'));
        } elseif (is_array($rawPath) && !empty($rawPath)) {
            $first = reset($rawPath);
            if (is_string($first)) {
                $initialUrl = str_starts_with($first, 'http') ? $first : asset('storage/' . ltrim($first, '/'));
            }
        }
    }
@endphp

<div
    x-data="{
        fallbackUrl: @js($initialUrl),
        get isStamped() {
            return !!$wire.get('{{ $parentPath }}.is_stamped');
        },
        get lat() {
            let v = $wire.get('{{ $parentPath }}.latitude');
            return v ? Number(v).toFixed(6) : '-6.894561';
        },
        get lng() {
            let v = $wire.get('{{ $parentPath }}.longitude');
            return v ? Number(v).toFixed(6) : '110.637492';
        },
        get time() {
            let t = $wire.get('{{ $parentPath }}.captured_at');
            if (!t) return '{{ now()->translatedFormat('d F Y H:i') }}';
            return t;
        },
        get caption() {
            let c = $wire.get('{{ $parentPath }}.caption');
            return c && c.trim() ? c : 'Dokumentasi Pelaksanaan Tugas';
        },
        get photoUrl() {
            let path = $wire.get('{{ $parentPath }}.file_path');
            if (!path) return this.fallbackUrl;
            if (typeof path === 'string') {
                if (path.startsWith('http://') || path.startsWith('https://')) return path;
                return '/storage/' + path.replace(/^public\//, '');
            }
            if (Array.isArray(path) && path.length > 0) {
                let first = path[0];
                if (typeof first === 'string') {
                    if (first.startsWith('http://') || first.startsWith('https://')) return first;
                    return '/storage/' + first.replace(/^public\//, '');
                }
            }
            return this.fallbackUrl;
        }
    }"
    class="w-full mt-2"
>
    <template x-if="photoUrl">
        <div class="relative w-full rounded-xl overflow-hidden border border-gray-300 dark:border-gray-700 shadow-md bg-gray-950 flex items-center justify-center min-h-[220px]">
            {{-- Foto Utama --}}
            <img
                :src="photoUrl"
                alt="Preview Foto Bukti Dukung"
                class="w-full max-h-[380px] object-contain rounded-lg"
            />

            {{-- Watermark Overlay di Atas Gambar (Pojok Kiri Bawah) --}}
            <div
                x-show="!isStamped"
                class="absolute bottom-2.5 left-2.5 max-w-[92%] sm:max-w-[78%] bg-black/80 backdrop-blur-[3px] border border-white/20 text-white rounded-lg p-2.5 shadow-2xl pointer-events-none transition-all"
            >
                <div class="flex items-center justify-between border-b border-white/20 pb-1 mb-1 text-[9px] font-sans font-bold text-emerald-400">
                    <span>🏷️ STEMPEL WATERMARK RESMI</span>
                    <span class="px-1 py-0.2 bg-emerald-950 text-emerald-300 rounded border border-emerald-500/40 text-[8px]">Aktif</span>
                </div>
                <div class="text-[10px] font-mono leading-relaxed space-y-0.5">
                    <div class="text-yellow-300 font-semibold flex items-center gap-1 truncate">
                        <span>📍</span>
                        <span>Lat:</span> <span x-text="lat"></span>
                        <span class="ml-1">Long:</span> <span x-text="lng"></span>
                    </div>
                    <div class="text-yellow-200 flex items-center gap-1 truncate text-[10px]">
                        <span>🕒</span>
                        <span x-text="time"></span>
                        <span>WIB</span>
                    </div>
                    <div class="text-white font-medium truncate max-w-full text-[10px]" x-text="'📝 ' + caption"></div>
                    <div class="text-emerald-400 font-bold tracking-wider text-[9px] pt-0.5">
                        🏛️ BPS KABUPATEN DEMAK
                    </div>
                </div>
            </div>

            {{-- Badge jika sudah ada stempel bawaan / sudah dibakar --}}
            <div
                x-show="isStamped"
                class="absolute bottom-2.5 left-2.5 bg-emerald-950/90 border border-emerald-500 text-emerald-300 rounded-lg px-2.5 py-1 text-[10px] font-bold shadow-lg flex items-center gap-1.5"
            >
                <span>✅</span>
                <span>Watermark Bawaan Kamera (Open Camera) / Fisik Aktif</span>
            </div>
        </div>
    </template>

    <template x-if="!photoUrl">
        <div class="w-full p-4 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/30 text-center text-xs text-gray-400 flex flex-col items-center justify-center gap-1.5 min-h-[140px]">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span>Pilih atau unggah foto untuk melihat preview visual stempel di atas foto</span>
        </div>
    </template>
</div>
