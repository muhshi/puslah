@php
    $reports = $record->sharedWorkReports;
    if ($reports->isEmpty() && $record->sharedWorkReport) {
        $reports = collect([$record->sharedWorkReport]);
    }
@endphp

<div 
    class="flex flex-wrap items-center gap-1.5 mt-1.5"
    x-on:click.stop
    x-on:mousedown.stop
>
    @forelse($reports as $rep)
        @php
            $shareUrl = route('shared-work-report.show', ['uuid' => $rep->uuid]);
        @endphp
        <div
            x-data="{ copied: false }"
            class="inline-flex items-center"
            x-on:click.stop
            x-on:mousedown.stop
        >
            <x-filament::badge
                color="success"
                size="sm"
                class="inline-flex items-center gap-1.5 py-0.5 px-2 select-none shadow-xs"
            >
                {{-- Tombol Judul Wadah (Buka Modal Kelola Wadah) --}}
                <span
                    role="button"
                    tabindex="0"
                    x-on:click.stop.prevent="$wire.mountTableAction('manage_wadah', '{{ $record->id }}')"
                    title="Klik untuk memilih/mengubah wadah kegiatan ini"
                    class="inline-flex items-center gap-1 hover:underline cursor-pointer focus:outline-hidden font-medium"
                >
                    <x-heroicon-m-folder class="w-3.5 h-3.5 shrink-0" />
                    <span class="max-w-[180px] truncate">{{ $rep->title }}</span>
                </span>

                {{-- Garis Pemisah --}}
                <span class="opacity-30 select-none text-[10px]">|</span>

                {{-- Tombol Copy Link Publik --}}
                <button
                    type="button"
                    x-on:click.stop.prevent="
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
                    title="Salin tautan publik wadah ini (untuk E-Kinerja)"
                    class="inline-flex items-center hover:opacity-75 cursor-pointer focus:outline-hidden transition-opacity"
                >
                    <span x-show="!copied" class="inline-flex items-center">
                        <x-heroicon-m-clipboard-document class="w-3.5 h-3.5" />
                    </span>
                    <span x-show="copied" x-cloak class="font-bold text-[10px] text-primary-600 dark:text-primary-400">
                        ✓ Disalin!
                    </span>
                </button>
            </x-filament::badge>
        </div>
    @empty
        <div 
            class="inline-flex items-center" 
            x-on:click.stop
            x-on:mousedown.stop
        >
            <x-filament::badge
                color="gray"
                size="sm"
                tag="button"
                type="button"
                x-on:click.stop.prevent="$wire.mountTableAction('manage_wadah', '{{ $record->id }}')"
                title="Klik untuk memilih wadah KIPAPP untuk kegiatan ini"
                icon="heroicon-m-plus-circle"
                class="hover:ring-1 hover:ring-primary-500 dark:hover:ring-primary-400 transition-all select-none shadow-xs cursor-pointer"
            >
                + Masuk Wadah
            </x-filament::badge>
        </div>
    @endforelse
</div>
