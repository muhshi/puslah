<x-filament::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-4">
            <div class="flex-1">
                <h2 class="text-lg font-bold tracking-tight text-gray-950 dark:text-white">
                    Monitor Nomor Surat
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Daftar nomor urut yang belum digunakan atau terlewat.
                </p>
            </div>

            <div class="w-32">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="selectedYear">
                        @foreach ($this->getAvailableYears() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>

        <div class="mt-4">
            @if ($this->getSkippedNumbers())
                <div class="rounded-lg bg-warning-50 p-4 dark:bg-warning-950/50">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <x-heroicon-m-exclamation-triangle class="h-5 w-5 text-warning-400" />
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-warning-800 dark:text-warning-200">
                                Nomor Terlewat (Tahun {{ $selectedYear }})
                            </h3>
                            <div class="mt-2 text-sm text-warning-700 dark:text-warning-300">
                                <p>
                                    {{ $this->getFormattedSkippedNumbers() }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-lg bg-success-50 p-4 dark:bg-success-950/50">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <x-heroicon-m-check-circle class="h-5 w-5 text-success-400" />
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-success-800 dark:text-success-200">
                                Tidak ada nomor yang terlewat di tahun {{ $selectedYear }}.
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament::widget>