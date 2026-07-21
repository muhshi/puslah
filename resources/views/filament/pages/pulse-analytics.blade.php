<x-filament-panels::page>
    <div class="space-y-6">
        {!! \Laravel\Pulse\Facades\Pulse::css() !!}

        <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <livewire:pulse.period-selector />
            </div>
        </div>

        <div class="grid grid-cols-12 gap-6">
            <livewire:pulse.servers cols="full" />
            <livewire:pulse.usage cols="4" rows="2" />
            <livewire:pulse.queues cols="4" />
            <livewire:pulse.cache cols="4" />
            <livewire:pulse.slow-queries cols="8" />
            <livewire:pulse.exceptions cols="6" />
            <livewire:pulse.slow-requests cols="6" />
            <livewire:pulse.slow-jobs cols="6" />
            <livewire:pulse.slow-outgoing-requests cols="6" />
        </div>

        {!! \Laravel\Pulse\Facades\Pulse::js() !!}
    </div>
</x-filament-panels::page>

