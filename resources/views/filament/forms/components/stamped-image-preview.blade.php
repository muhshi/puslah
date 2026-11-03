@php
    $statePath = $getStatePath();
    $lastDot = strrpos($statePath, '.');
    $parentPath = $lastDot !== false ? substr($statePath, 0, $lastDot) : $statePath;
    
    // Ambil initial url saat mode edit record jika file sudah ada
    $initialUrl = null;
    try {
        $record = isset($getRecord) && is_callable($getRecord) ? $getRecord() : null;
        if ($record && !empty($record->file_path)) {
            $raw = $record->file_path;
            $initialUrl = str_starts_with($raw, 'http') ? $raw : asset('storage/' . ltrim($raw, '/'));
        }
    } catch (\Throwable $e) {
        $initialUrl = null;
    }
@endphp

<div
    x-data="{
        fallbackUrl: @js($initialUrl),
        get isStamped() {
            return !!$wire.get('{{ $parentPath }}.is_stamped');
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
        },
        get isPdf() {
            let url = this.photoUrl;
            if (!url) return false;
            return url.toLowerCase().includes('.pdf');
        }
    }"
    class="w-full mt-1.5"
>
    {{-- 1. Kartu Dokumen PDF --}}
    <template x-if="photoUrl && isPdf">
        <div class="w-full rounded-xl p-3.5 border border-red-300 dark:border-red-900/60 bg-red-50/70 dark:bg-red-950/30 shadow-xs flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-red-600 text-white flex items-center justify-center flex-shrink-0 font-bold text-xs shadow-xs">
                    PDF
                </div>
                <div>
                    <span class="text-xs font-bold text-slate-900 dark:text-white">Dokumen Bukti Dukung PDF</span>
                    <p class="text-[11px] text-slate-500 mt-0.5">Berkas dokumen PDF siap dilampirkan ke laporan.</p>
                </div>
            </div>

            <a
                :href="photoUrl"
                target="_blank"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-600 hover:bg-red-700 text-white shadow-xs transition-colors flex-shrink-0"
            >
                Buka PDF ↗
            </a>
        </div>
    </template>

    {{-- 2. Status Stempel Foto (Kompak & Bersih, Tanpa Dobel Gambar) --}}
    <template x-if="photoUrl && !isPdf">
        <div class="flex items-center gap-2 p-2.5 rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50/60 dark:bg-emerald-950/30 text-emerald-800 dark:text-emerald-300 text-xs">
            <span class="text-emerald-600 font-bold">✅</span>
            <span class="font-medium">
                Stempel Resmi BPS (Koordinat & Waktu) otomatis tercetak rapi di pojok kiri bawah file foto (Open Camera style).
            </span>
        </div>
    </template>
</div>
