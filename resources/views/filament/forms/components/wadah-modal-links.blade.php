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
        <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center gap-1.5">
            <span>🔗</span> Tautan Berkas Publik Wadah Terpilih (Siap Disalin ke E-Kinerja):
        </div>
        <div class="space-y-2">
            @foreach($reports as $rep)
                @php
                    $url = route('shared-work-report.show', ['uuid' => $rep->uuid]);
                @endphp
                <div 
                    x-data="{ copied: false }"
                    class="flex items-center justify-between p-2.5 rounded-lg border border-emerald-200 dark:border-emerald-800/60 bg-emerald-50/60 dark:bg-emerald-950/20 text-xs gap-2"
                >
                    <div class="flex items-center gap-2 min-w-0 pr-1">
                        <span class="p-1 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300 flex-shrink-0">
                            <x-heroicon-m-folder class="w-4 h-4" />
                        </span>
                        <div class="min-w-0">
                            <span class="font-bold text-slate-900 dark:text-white block truncate">{{ $rep->title }}</span>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400 block truncate font-mono">{{ $url }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        <button
                            type="button"
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
                            class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 font-semibold text-xs flex items-center gap-1.5 shadow-xs transition-colors cursor-pointer"
                        >
                            <span x-show="!copied" class="flex items-center gap-1">
                                <x-heroicon-m-clipboard class="w-3.5 h-3.5 text-slate-600 dark:text-slate-400" /> Salin Link
                            </span>
                            <span x-show="copied" x-cloak class="text-emerald-600 font-bold flex items-center gap-1">
                                ✓ Disalin!
                            </span>
                        </button>

                        <a
                            href="{{ $url }}"
                            target="_blank"
                            class="px-2.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs flex items-center gap-1 shadow-xs transition-colors"
                        >
                            <x-heroicon-m-arrow-top-right-on-square class="w-3.5 h-3.5" /> Buka
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="p-3 rounded-lg border border-dashed border-slate-300 dark:border-slate-700 text-center text-xs text-slate-500 dark:text-slate-400 bg-slate-50/50 dark:bg-slate-900/20">
            💡 <em>Pilih satu atau lebih wadah di atas untuk melihat dan menyalin tautan publik berkas KIPAPP.</em>
        </div>
    @endif
</div>
