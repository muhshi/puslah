<x-filament-panels::page>
    @if ($isAdmin)
        <div class="mb-4 flex gap-2">
            <x-filament::button tag="a" :href="\App\Filament\Resources\LeaveResource::getUrl()">Approve
                Cuti</x-filament::button>
            <x-filament::button tag="a" color="success" :href="\App\Filament\Resources\AttendanceResource::getUrl()">Data
                Presensi</x-filament::button>
        </div>
    @endif

    @if (empty($isAdmin) || !$isAdmin || !$isPegawai)
        <x-filament::section>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-lg font-semibold">Presensi</div>
                    <div class="text-sm text-gray-600">Klik tombol di bawah untuk melakukan check-in/out.</div>
                </div>
                <x-filament::button tag="a" href="{{ url('/presensi') }}" icon="heroicon-o-finger-print">
                    Ke Halaman Presensi
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    <x-filament-widgets::widgets :widgets="$this->getWidgets()" />
</x-filament-panels::page>