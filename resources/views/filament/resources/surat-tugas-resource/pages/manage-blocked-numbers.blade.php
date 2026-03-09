<x-filament-panels::page>
    {{-- Block Form --}}
    <form wire:submit="blockNumbers">
        {{ $this->blockingForm }}

        <div class="mt-6">
            <x-filament::button type="submit" size="lg" color="danger" icon="heroicon-o-lock-closed">
                Block Nomor
            </x-filament::button>
        </div>
    </form>

    {{-- Table of blocked numbers --}}
    <div class="mt-8">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
