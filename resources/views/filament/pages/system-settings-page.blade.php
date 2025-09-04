<x-filament::page>
    <div>
        {{ $this->form }}
    </div>

    <x-slot name="footer">
        <x-filament::button wire:click="save">
            Simpan
        </x-filament::button>
    </x-slot>
</x-filament::page>
