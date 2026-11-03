@php
    $reports = $record->sharedWorkReports;
    if ($reports->isEmpty() && $record->sharedWorkReport) {
        $reports = collect([$record->sharedWorkReport]);
    }
@endphp

<div class="flex flex-wrap items-center gap-1.5 mt-1">
    @forelse($reports as $rep)
        @php
            $shareUrl = route('shared-work-report.show', ['uuid' => $rep->uuid]);
        @endphp
        <div
            x-data="{ copied: false }"
            class="inline-block"
        >
            <button
                type="button"
                @click.stop="
                    navigator.clipboard.writeText('{{ $shareUrl }}');
                    copied = true;
                    setTimeout(() => copied = false, 2000);
                "
                title="Klik untuk salin tautan publik bukti dukung KIPAPP"
                class="transition-transform active:scale-95 cursor-pointer focus:outline-none"
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
        <x-filament::badge color="danger" size="sm" icon="heroicon-m-x-circle">
            Belum Ada Wadah KIPAPP
        </x-filament::badge>
    @endforelse
</div>
