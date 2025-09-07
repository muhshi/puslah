<x-filament-panels::page>
    <div class="mb-4 flex gap-2">
        <x-filament::button tag="a" :href="\App\Filament\Resources\LeaveResource::getUrl()">Approve Cuti</x-filament::button>
        <x-filament::button tag="a" color="success" :href="\App\Filament\Resources\AttendanceResource::getUrl()">Data Presensi</x-filament::button>
    </div>

    <x-filament-widgets::widgets :widgets="$this->getWidgets()" />
</x-filament-panels::page>
