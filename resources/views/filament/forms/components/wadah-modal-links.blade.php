@php
    $selectedIds = $get('shared_work_reports') ?? [];
    if (!is_array($selectedIds)) {
        $selectedIds = [$selectedIds];
    }
    $selectedIds = array_filter(array_map('intval', $selectedIds));
    $reports = !empty($selectedIds) 
        ? \App\Models\SharedWorkReport::whereIn('id', $selectedIds)->get()
        : collect();
@endphp

<div class="w-full mt-2">
    @if($reports->isNotEmpty())
        <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-1.5">
            <span>🔗</span> <span>Tautan Berkas Publik Wadah Terpilih (Siap Disalin ke E-Kinerja):</span>
        </div>
        <div class="space-y-2">
            @foreach($reports as $rep)
                @php
                    $url = route('shared-work-report.show', ['uuid' => $rep->uuid]);
                @endphp
                <div 
                    x-data="{ copied: false }"
                    class="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-900/40 text-xs gap-3"
                >
                    <div class="flex items-center gap-2.5 min-w-0 pr-1">
                        <x-filament::badge color="success" size="sm" icon="heroicon-m-folder" />
                        <div class="min-w-0">
                            <span class="font-bold text-gray-900 dark:text-white block truncate">{{ $rep->title }}</span>
                            <span class="text-[11px] text-gray-500 dark:text-gray-400 block truncate font-mono">{{ $url }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-shrink-0">
                        <x-filament::button
                            type="button"
                            size="xs"
                            color="gray"
                            icon="heroicon-m-clipboard-document"
                            @click="
                                if (navigator.clipboard && navigator.clipboard.writeText) {
                                    navigator.clipboard.writeText('{{ $url }}');
                                } else {
                                    const el = document.createElement('textarea');
                                    el.value = '{{ $url }}';
                                    document.body.appendChild(el);
                                    el.select();
                                    document.execCommand('copy');
                                    document.body.removeChild(el);
                                }
                                copied = true;
                                setTimeout(() => copied = false, 2500);
                            "
                        >
                            <span x-show="!copied">Salin Link</span>
                            <span x-show="copied" x-cloak class="font-bold text-success-600 dark:text-success-400">✓ Disalin!</span>
                        </x-filament::button>

                        <x-filament::button
                            size="xs"
                            color="success"
                            icon="heroicon-m-arrow-top-right-on-square"
                            tag="a"
                            :href="$url"
                            target="_blank"
                        >
                            Buka
                        </x-filament::button>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="p-3 rounded-lg border border-dashed border-gray-300 dark:border-gray-700 text-center text-xs text-gray-500 dark:text-gray-400 bg-gray-50/50 dark:bg-gray-900/20">
            💡 <em>Pilih satu atau lebih wadah di atas untuk melihat dan menyalin tautan publik berkas KIPAPP.</em>
        </div>
    @endif
</div>
