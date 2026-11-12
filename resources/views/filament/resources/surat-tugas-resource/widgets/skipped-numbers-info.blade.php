<x-filament::widget>
    <x-filament::section>
        @php
            $stSkippedCount = $this->getSkippedNumbersCount();
            $sppdSkippedCount = $this->getSppdSkippedNumbersCount();
            $missingByMonth = $activeTab === 'sppd'
                ? $this->getSppdSkippedNumbersByMonth()
                : $this->getSkippedNumbersByMonth();
            $blockedGroups = $this->getBlockedGroupsDetails();
            $activeSkippedCount = $activeTab === 'sppd' ? $sppdSkippedCount : $stSkippedCount;
        @endphp

        {{-- Header: Title, Native Filament Tabs, Year Selector --}}
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="flex-1">
                <h2 class="text-lg font-bold tracking-tight text-gray-950 dark:text-white">
                    Monitor Nomor Surat
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Daftar nomor urut yang belum digunakan atau terlewat.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <x-filament::tabs label="Jenis Dokumen">
                    <x-filament::tabs.item
                        :active="$activeTab === 'surat_tugas'"
                        wire:click="setActiveTab('surat_tugas')"
                        :badge="$stSkippedCount > 0 ? $stSkippedCount : '✓'"
                        :badge-color="$stSkippedCount > 0 ? 'warning' : 'success'"
                    >
                        Surat Tugas
                    </x-filament::tabs.item>

                    <x-filament::tabs.item
                        :active="$activeTab === 'sppd'"
                        wire:click="setActiveTab('sppd')"
                        :badge="$sppdSkippedCount > 0 ? $sppdSkippedCount : '✓'"
                        :badge-color="$sppdSkippedCount > 0 ? 'warning' : 'success'"
                    >
                        SPPD
                    </x-filament::tabs.item>
                </x-filament::tabs>

                <div class="w-28">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="selectedYear">
                            @foreach ($this->getAvailableYears() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            </div>
        </div>

        <div class="mt-3 space-y-4">
            {{-- Bagian Nomor Terlewat --}}
            <div class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                        Belum Terpakai / Terlewat {{ $activeTab === 'sppd' ? '(SPPD)' : '(Surat Tugas)' }}
                    </h3>

                    @if ($activeSkippedCount > 0)
                        <x-filament::badge color="warning" class="whitespace-nowrap">
                            Total {{ $activeSkippedCount }} nomor terlewat
                        </x-filament::badge>
                    @endif
                </div>

                @if (!empty($missingByMonth))
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                        @foreach ($missingByMonth as $month => $ranges)
                            @php
                                $tokens = array_filter(array_map('trim', explode(',', $ranges)));
                            @endphp
                            <div class="rounded-xl bg-gray-50 dark:bg-white/5 p-3 ring-1 ring-gray-950/5 dark:ring-white/10 flex flex-col gap-2">
                                <div class="flex items-center justify-between gap-2 border-b border-gray-200 dark:border-white/10 pt-2">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-danger-600 dark:text-danger-400 mb-2">
                                        {{ $month }}
                                    </h4>
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap mb-2">
                                        {{ count($tokens) }} segmen
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($tokens as $token)
                                        <x-filament::badge color="warning" class="font-mono whitespace-nowrap">
                                            {{ $token }}
                                        </x-filament::badge>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-xl bg-gray-50 dark:bg-white/5 p-4 ring-1 ring-gray-950/5 dark:ring-white/10">
                        <div class="flex items-center gap-3">
                            <x-heroicon-m-check-circle class="h-5 w-5 text-success-600 dark:text-success-400 shrink-0" />
                            <p class="text-sm font-medium text-gray-950 dark:text-white">
                                Tidak ada nomor {{ $activeTab === 'sppd' ? 'SPPD' : 'Surat Tugas' }} yang terlewat di tahun {{ $selectedYear }}.
                            </p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Bagian Nomor Di-block (khusus Surat Tugas) --}}
            @if ($activeTab === 'surat_tugas' && !empty($blockedGroups))
                <div class="rounded-xl bg-gray-50 dark:bg-white/5 p-4 ring-1 ring-gray-950/5 dark:ring-white/10 space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <x-heroicon-m-lock-closed class="h-5 w-5 text-danger-600 dark:text-danger-400 shrink-0" />
                            <div>
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                                    Nomor Di-block (Khusus / Reserved)
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Tahun {{ $selectedYear }} &bull; {{ count($blockedGroups) }} grup terblokir
                                </p>
                            </div>
                        </div>

                        <x-filament::button
                            href="{{ App\Filament\Resources\SuratTugasResource::getUrl('manage-blocked-numbers') }}"
                            tag="a"
                            color="gray"
                            size="sm"
                            icon="heroicon-m-cog-6-tooth"
                        >
                            Kelola Semua
                        </x-filament::button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($blockedGroups as $group)
                            <div class="flex flex-col justify-between rounded-xl bg-white dark:bg-gray-900 p-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 gap-3">
                                <div class="space-y-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <h5 class="text-sm font-semibold text-gray-950 dark:text-white line-clamp-2" title="{{ $group['keterangan'] }}">
                                            {{ $group['keterangan'] }}
                                        </h5>
                                        <x-filament::badge color="danger" class="whitespace-nowrap shrink-0">
                                            {{ $group['count'] }} nomor
                                        </x-filament::badge>
                                    </div>

                                    <div class="rounded-lg bg-gray-50 dark:bg-white/5 px-3 py-2 ring-1 ring-gray-950/5 dark:ring-white/10">
                                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                            Rentang Nomor
                                        </div>
                                        <div class="text-sm font-mono font-bold text-primary-600 dark:text-primary-400 break-words mt-2">
                                            {{ $group['ranges'] }}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 pt-2 border-t border-gray-200 dark:border-white/10">
                                    <x-filament::button
                                        wire:click="mountAction('buatSuratTugas', { keterangan: '{{ addslashes($group['keterangan']) }}' })"
                                        color="success"
                                        size="xs"
                                        icon="heroicon-m-document-plus"
                                        class="flex-1"
                                    >
                                        Buat ST
                                    </x-filament::button>

                                    <x-filament::button
                                        wire:click="mountAction('releaseBlockedGroup', { keterangan: '{{ addslashes($group['keterangan']) }}' })"
                                        color="warning"
                                        size="xs"
                                        icon="heroicon-m-lock-open"
                                        outlined
                                    >
                                        Release
                                    </x-filament::button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament::widget>
