@php
    $statePath = $getStatePath();
    $lastDot = strrpos($statePath, '.');
    $parentPath = $lastDot !== false ? substr($statePath, 0, $lastDot) : $statePath;
@endphp

<div
    x-data="{
        get isStamped() {
            return !!$wire.get('{{ $parentPath }}.is_stamped');
        },
        get lat() {
            return $wire.get('{{ $parentPath }}.latitude') || '-6.894561';
        },
        get lng() {
            return $wire.get('{{ $parentPath }}.longitude') || '110.637492';
        },
        get time() {
            let t = $wire.get('{{ $parentPath }}.captured_at');
            return t ? t : '{{ now()->translatedFormat('d F Y H:i') }}';
        },
        get caption() {
            return $wire.get('{{ $parentPath }}.caption') || 'Dokumentasi Kegiatan BPS';
        },
        get photoUrl() {
            let path = $wire.get('{{ $parentPath }}.file_path');
            if (!path) return null;
            if (typeof path === 'string') {
                if (path.startsWith('http://') || path.startsWith('https://')) return path;
                return '/storage/' + path.replace(/^public\//, '');
            }
            if (Array.isArray(path) && path.length > 0) {
                let first = path[0];
                if (typeof first === 'string') return '/storage/' + first.replace(/^public\//, '');
            }
            return null;
        }
    }"
    class="w-full mt-3"
>
    <template x-if="photoUrl">
        <div class="relative w-full rounded-xl overflow-hidden border border-gray-300 dark:border-gray-700 shadow-md bg-gray-950 flex items-center justify-center min-h-[220px]">
            {{-- Foto Utama --}}
            <img
                :src="photoUrl"
                alt="Preview Foto Berstempel"
                class="w-full max-h-[380px] object-contain rounded-lg"
            />

            {{-- Watermark Overlay di Atas Gambar (Kiri Bawah) --}}
            <div
                x-show="!isStamped"
                class="absolute bottom-2.5 left-2.5 max-w-[92%] sm:max-w-[78%] bg-black/80 backdrop-blur-[3px] border border-white/20 text-white rounded-lg p-2.5 shadow-2xl pointer-events-none transition-all"
            >
                <div class="flex items-center justify-between border-b border-white/20 pb-1 mb-1 text-[9px] font-sans font-bold text-emerald-400">
                    <span>🏷️ STEMPEL WATERMARK FOTO</span>
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

            {{-- Badge jika sudah ada stempel bawaan (misal Open Camera) --}}
            <div
                x-show="isStamped"
                class="absolute bottom-2.5 left-2.5 bg-emerald-950/90 border border-emerald-500 text-emerald-300 rounded-lg px-2.5 py-1 text-[10px] font-bold shadow-lg flex items-center gap-1.5"
            >
                <span>✅</span>
                <span>Watermark Bawaan Kamera (Open Camera) Aktif</span>
            </div>
        </div>
    </template>

    <template x-if="!photoUrl">
        <div class="w-full p-4 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/30 text-center text-xs text-gray-400 flex flex-col items-center justify-center gap-1.5 min-h-[140px]">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span>Pilih atau unggah foto di atas untuk melihat preview visual stempel</span>
        </div>
    </template>
</div>
