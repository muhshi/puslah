@php
    $photoFields = [
        ['field' => 'foto_1', 'label' => 'Foto 1 (Wajib)'],
        ['field' => 'foto_2', 'label' => 'Foto 2 (Wajib)'],
        ['field' => 'foto_3', 'label' => 'Foto 3 (Opsional)'],
        ['field' => 'foto_4', 'label' => 'Foto 4 (Opsional)'],
    ];

    $existingPhotos = [];
    foreach ($photoFields as $item) {
        $val = $record->{$item['field']};
        if (!empty($val)) {
            $existingPhotos[] = [
                'field' => $item['field'],
                'label' => $item['label'],
                'path' => $val,
            ];
        }
    }
@endphp

<div class="space-y-4">
    @if(empty($existingPhotos))
        <div class="flex flex-col items-center justify-center p-8 text-center text-gray-500 dark:text-gray-400">
            <svg class="w-12 h-12 mb-3 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
            </svg>
            <p class="text-sm font-medium">Belum ada foto dokumentasi lembur yang diunggah.</p>
        </div>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3 p-3 bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="text-sm text-gray-700 dark:text-gray-300">
                <span class="font-bold text-primary-600 dark:text-primary-400">{{ count($existingPhotos) }} Foto</span> terlampir untuk laporan lembur ini.
            </div>
            <div>
                <a href="{{ route('laporan-lembur.download-photos-zip', $record) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-primary-600 hover:bg-primary-500 rounded-lg shadow transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Download Semua (ZIP)
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[65vh] overflow-y-auto pr-1">
            @foreach($existingPhotos as $idx => $photo)
                @php
                    $url = \Illuminate\Support\Facades\Storage::disk('public')->url($photo['path']);
                    $ext = pathinfo($photo['path'], PATHINFO_EXTENSION) ?: 'jpg';
                    $downloadName = 'Foto_Lembur_' . ($idx + 1) . '_' . ($record->waktu ? \Carbon\Carbon::parse($record->waktu)->format('Ymd') : 'lembur') . '.' . $ext;
                    $downloadUrl = route('storage.download-file', ['path' => $photo['path'], 'name' => $downloadName]);
                @endphp
                <div class="flex flex-col bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm hover:shadow transition">
                    <div class="relative group bg-gray-100 dark:bg-gray-900 aspect-video overflow-hidden flex items-center justify-center">
                        <img src="{{ $url }}" 
                             alt="{{ $photo['label'] }}"
                             class="object-cover w-full h-full group-hover:scale-105 transition duration-300"
                             loading="lazy" />
                        <span class="absolute top-2 left-2 px-2 py-0.5 text-[11px] font-semibold bg-black/60 text-white rounded-md backdrop-blur-sm">
                            {{ $photo['label'] }}
                        </span>
                    </div>

                    <div class="p-3 flex items-center justify-between gap-2 border-t border-gray-100 dark:border-gray-700/60">
                        <a href="{{ $url }}" 
                           target="_blank" 
                           rel="noopener noreferrer"
                           class="flex-1 inline-flex items-center justify-center gap-1 px-2.5 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition"
                           title="Lihat ukuran penuh di tab baru">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            Buka
                        </a>

                        <a href="{{ $downloadUrl }}"
                           class="flex-1 inline-flex items-center justify-center gap-1 px-2.5 py-1.5 text-xs font-medium text-white bg-primary-600 hover:bg-primary-500 rounded-lg shadow-sm transition"
                           title="Download file foto lembur">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Download
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
