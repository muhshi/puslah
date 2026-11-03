@php
    $reports = $record->sharedWorkReports;
    if ($reports->isEmpty() && $record->sharedWorkReport) {
        $reports = collect([$record->sharedWorkReport]);
    }
@endphp

<div class="flex flex-wrap items-center gap-1.5 mt-1">
    @forelse($reports as $rep)
        <x-filament::badge color="success" size="sm" icon="heroicon-m-folder">
            {{ \Illuminate\Support\Str::limit($rep->title, 40) }}
        </x-filament::badge>
    @empty
        <x-filament::badge color="danger" size="sm" icon="heroicon-m-x-circle">
            Belum Ada Wadah KIPAPP
        </x-filament::badge>
    @endforelse
</div>
