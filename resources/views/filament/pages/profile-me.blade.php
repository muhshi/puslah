<x-filament-panels::page>
    {{-- Form Profil --}}
    <form wire:submit.prevent="saveProfile">
        {{ $this->profileForm }}

        <div class="pt-4">
            <x-filament::button type="submit">Simpan Profil</x-filament::button>
        </div>
    </form>

    <hr class="my-6 border-gray-200 dark:border-gray-700" />

    {{-- Form Password --}}
    <form wire:submit.prevent="savePassword">
        {{ $this->passwordForm }}

        <div class="pt-4">
            <x-filament::button type="submit" color="warning">Update Password</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
