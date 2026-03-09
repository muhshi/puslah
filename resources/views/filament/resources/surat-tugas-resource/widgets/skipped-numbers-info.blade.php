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

        <div class="mt-4 space-y-4">
            {{-- Bagian Nomor Terlewat --}}
            @php
                $missingByMonth = $this->getSkippedNumbersByMonth();
            @endphp

            @if (!empty($missingByMonth))
                <div>
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Belum Terpakai / Terlewat</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                        @foreach ($missingByMonth as $month => $ranges)
                            <div
                                class="rounded-md bg-warning-50 p-2 dark:bg-warning-950/50 border border-warning-200 dark:border-warning-900">
                                <h4
                                    class="text-xs font-semibold uppercase tracking-wider text-danger-600 dark:text-danger-400 mb-0.5">
                                    {{ $month }}
                                </h4>
                                <div class="text-xs font-bold text-warning-700 dark:text-warning-300 break-words leading-tight">
                                    {{ $ranges }}
                                </div>
                            </div>
                        @endforeach
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

            {{-- Bagian Nomor Di-block --}}
            @php
                $blockedGroups = $this->getBlockedNumbersGrouped();
            @endphp
            @if (!empty($blockedGroups))
                <div>
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 mt-4">Nomor Di-block (Khusus / Reserved)</h3>
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between rounded-lg bg-gray-50 border border-gray-200 p-4 dark:bg-gray-900 dark:border-gray-800 gap-4">
                        <div class="flex-1 w-full">
                            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-3">TAHUN {{ $selectedYear }}</h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach ($blockedGroups as $keterangan => $ranges)
                                    <div class="rounded-md bg-white p-3 shadow-sm border border-gray-100 dark:bg-gray-800 dark:border-gray-700">
                                        <h5 class="text-xs font-semibold text-gray-800 dark:text-gray-200 mb-1 line-clamp-2" title="{{ $keterangan }}">
                                            {{ $keterangan }}
                                        </h5>
                                        <div class="text-sm font-bold text-primary-600 dark:text-primary-400 break-words">
                                            {{ $ranges }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        
                        <div class="sm:ml-4 flex-shrink-0 mt-2 sm:mt-0">
                            <x-filament::button
                                href="{{ App\Filament\Resources\SuratTugasResource::getUrl('manage-blocked-numbers') }}"
                                tag="a"
                                color="success"
                                size="sm"
                                icon="heroicon-m-document-plus"
                            >
                                Kelola & Buat ST
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament::widget>