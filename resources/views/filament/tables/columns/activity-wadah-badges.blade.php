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
            class="inline-block relative z-10"
            @click.stop="$event.stopPropagation()"
            @mousedown.stop="$event.stopPropagation()"
        >
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
                @mousedown.stop="$event.stopPropagation()"
                @mouseup.stop="$event.stopPropagation()"
                title="Klik untuk salin tautan publik bukti dukung KIPAPP"
                class="transition-transform active:scale-95 cursor-pointer focus:outline-none select-none"
            >
                <span x-show="!copied">
                    <x-filament::badge color="success" size="sm" icon="heroicon-m-folder">
                        {{ \Illuminate\Support\Str::limit($rep->title, 35) }}
                    </x-filament::badge>
                </span>
                <span x-show="copied" x-cloak>
                    <x-filament::badge color="info" size="sm" icon="heroicon-m-clipboard-document-check">
                        Tautan Disalin!
                    </x-filament::badge>
                </span>
            </button>
        </div>
    @empty
        <x-filament::badge color="gray" size="sm" icon="heroicon-m-folder-open">
            Belum Masuk Wadah
        </x-filament::badge>
    @endforelse
</div>
