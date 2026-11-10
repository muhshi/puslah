@php
    $reports = $record->sharedWorkReports;
    if ($reports->isEmpty() && $record->sharedWorkReport) {
        $reports = collect([$record->sharedWorkReport]);
    }
@endphp

<div 
    class="flex flex-wrap items-center gap-1.5 mt-1"
    @click.stop="$event.stopPropagation()"
    @mousedown.stop="$event.stopPropagation()"
>
    @forelse($reports as $rep)
        @php
            $shareUrl = route('shared-work-report.show', ['uuid' => $rep->uuid]);
        @endphp
        <div
            x-data="{ copied: false }"
            class="inline-flex items-center rounded-md border border-emerald-300 dark:border-emerald-700/80 bg-emerald-50/90 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 shadow-xs overflow-hidden text-[11px] leading-tight select-none"
            @click.stop="$event.stopPropagation()"
            @mousedown.stop="$event.stopPropagation()"
        >
            {{-- Bagian Kiri: Judul Wadah (Klik untuk buka modal pilih/atur wadah) --}}
            <button
                type="button"
                wire:click="mountTableAction('manage_wadah', '{{ $record->id }}')"
                @click.stop="$event.stopPropagation()"
                title="Klik untuk ubah / atur wadah kegiatan ini"
                class="flex items-center gap-1 px-2 py-0.5 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 transition-colors font-medium cursor-pointer"
            >
                <x-heroicon-m-folder class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
                <span class="max-w-[150px] truncate">{{ $rep->title }}</span>
            </button>

            {{-- Pemisah vertikal --}}
            <span class="w-[1px] h-3.5 bg-emerald-300 dark:bg-emerald-700"></span>

            {{-- Bagian Kanan: Tombol Salin Link Publik --}}
            <button
                type="button"
                @click.stop.prevent="
                    $event.stopPropagation();
                    $event.preventDefault();
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText('{{ $shareUrl }}');
                    } else {
                        const el = document.createElement('textarea');
                        el.value = '{{ $shareUrl }}';
                        document.body.appendChild(el);
                        el.select();
                        document.execCommand('copy');
                        document.body.removeChild(el);
                    }
                    copied = true;
                    setTimeout(() => copied = false, 2000);
                "
                title="Salin tautan publik bukti dukung wadah ini (untuk E-Kinerja)"
                class="px-1.5 py-0.5 hover:bg-emerald-200/80 dark:hover:bg-emerald-900 transition-colors cursor-pointer text-emerald-700 dark:text-emerald-300 flex items-center"
            >
                <span x-show="!copied" class="flex items-center">
                    <x-heroicon-m-clipboard-document class="w-3.5 h-3.5" />
                </span>
                <span x-show="copied" x-cloak class="text-[10px] font-bold text-emerald-700 dark:text-emerald-300">
                    ✓ Disalin!
                </span>
            </button>
        </div>
    @empty
        <button
            type="button"
            wire:click="mountTableAction('manage_wadah', '{{ $record->id }}')"
            @click.stop="$event.stopPropagation()"
            title="Klik untuk memilih wadah KIPAPP untuk kegiatan ini"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md border border-dashed border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 text-slate-500 hover:text-emerald-700 hover:border-emerald-400 hover:bg-emerald-50/50 dark:hover:text-emerald-300 transition-colors text-[11px] cursor-pointer font-medium"
        >
            <x-heroicon-m-plus-circle class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-500" />
            <span>+ Masuk Wadah</span>
        </button>
    @endforelse
</div>
