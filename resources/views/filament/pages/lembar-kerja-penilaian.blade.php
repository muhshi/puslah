<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @php
            $survey = $this->getSelectedSurvey();
            $dataset = $this->getDataset();
            $weights = $dataset['weights'];
            $rows = $dataset['rows'];
            $officials = $dataset['class_officials'];
            $totalWeight = collect($weights)->sum('weight');
            $scoredCount = collect($rows)->filter(fn ($r) => $r['nilai'] !== null && $r['nilai'] !== '')->count();
            $avgScore = $scoredCount > 0
                ? round(collect($rows)->filter(fn ($r) => $r['nilai'] !== null && $r['nilai'] !== '')->avg('nilai'), 2)
                : 0;
        @endphp

        @if (!$survey)
            <x-filament::section>
                <p class="text-sm text-gray-500">Pilih pelatihan terlebih dahulu untuk membuka Lembar Kerja Penilaian.</p>
            </x-filament::section>
        @else
            {{-- Ringkasan & Skema Bobot --}}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <x-filament::section>
                    <div class="text-xs font-medium text-gray-500">Peserta Dinilai (PPL/PML)</div>
                    <div class="mt-1 text-2xl font-bold">{{ count($rows) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-xs font-medium text-gray-500">Sudah Ada Nilai</div>
                    <div class="mt-1 text-2xl font-bold text-success-600">{{ $scoredCount }} / {{ count($rows) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-xs font-medium text-gray-500">Rata-rata Nilai</div>
                    <div class="mt-1 text-2xl font-bold">{{ $avgScore }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-xs font-medium text-gray-500">Skema Bobot ({{ $totalWeight }}%)</div>
                    <div class="mt-1 text-xs text-gray-700 dark:text-gray-300">
                        @foreach ($weights as $w)
                            <span class="inline-block mr-2">{{ $w['label'] }}: <strong>{{ $w['weight'] }}%</strong></span>
                        @endforeach
                    </div>
                </x-filament::section>
            </div>

            {{-- Info Inda & Manajer Kelas / Panitia --}}
            @if (!empty($officials))
                <x-filament::section heading="Penanggung Jawab Kelas (Inda & Panitia)">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ($officials as $kelasNama => $info)
                            @if (!$this->kelasFilter || strcasecmp($kelasNama, $this->kelasFilter) === 0)
                                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                    <div class="text-sm font-semibold">{{ $kelasNama }}</div>
                                    <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                        <strong>Inda:</strong> {{ !empty($info['inda']) ? implode(', ', $info['inda']) : '-' }}
                                    </div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400">
                                        <strong>Manajer Kelas / Panitia:</strong> {{ !empty($info['panitia']) ? implode(', ', $info['panitia']) : '-' }}
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </x-filament::section>
            @endif

            {{-- Tabel Input Nilai --}}
            <x-filament::section>
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold">Daftar Nilai Peserta ({{ $survey->name }})</h3>
                        <p class="text-xs text-gray-500">Hanya menampilkan peserta PPL &amp; PML. Nilai akhir dihitung otomatis sesuai bobot, dengan status <code>is_lulus = 1</code>.</p>
                    </div>
                    <x-filament::button wire:click="saveAllScores" color="primary" size="sm">
                        Simpan Semua Nilai
                    </x-filament::button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-3 py-2 font-medium">No</th>
                                <th class="px-3 py-2 font-medium">NIP / NIK</th>
                                <th class="px-3 py-2 font-medium">Nama</th>
                                <th class="px-3 py-2 font-medium">Instansi</th>
                                <th class="px-3 py-2 font-medium">Tipe</th>
                                <th class="px-3 py-2 font-medium">Kelas</th>
                                <th class="px-3 py-2 font-medium">Role</th>
                                @foreach ($weights as $comp)
                                    <th class="px-3 py-2 font-medium text-center">
                                        {{ $comp['label'] }}<br>
                                        <span class="text-xs font-normal text-gray-500">({{ $comp['weight'] }}%)</span>
                                    </th>
                                @endforeach
                                <th class="px-3 py-2 font-medium text-center">Nilai Akhir</th>
                                <th class="px-3 py-2 font-medium text-center">Lulus</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($rows as $idx => $row)
                                @php $suId = $row['survey_user_id']; @endphp
                                <tr wire:key="lk-row-{{ $suId }}" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-3 py-2">{{ $idx + 1 }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $row['nip_nik'] }}</td>
                                    <td class="px-3 py-2 font-medium">{{ $row['nama'] }}</td>
                                    <td class="px-3 py-2 text-xs">{{ $row['instansi'] }}</td>
                                    <td class="px-3 py-2 text-xs">{{ $row['tipe_peserta'] }}</td>
                                    <td class="px-3 py-2 text-xs">{{ $row['kelas'] }}</td>
                                    <td class="px-3 py-2 text-xs">{{ $row['role'] }}</td>
                                    @foreach ($weights as $comp)
                                        <td class="px-2 py-1 text-center">
                                            <input
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                wire:model.lazy="scoresInput.{{ $suId }}.{{ $comp['key'] }}"
                                                wire:change="saveRowScore({{ $suId }})"
                                                class="w-20 rounded border-gray-300 px-2 py-1 text-center text-sm dark:border-gray-600 dark:bg-gray-900"
                                            />
                                        </td>
                                    @endforeach
                                    <td class="px-3 py-2 text-center font-bold">
                                        {{ $row['nilai'] !== null ? $row['nilai'] : '-' }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="inline-flex items-center rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-900/30 dark:text-success-400">
                                            1
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 9 + count($weights) }}" class="px-3 py-6 text-center text-gray-500">
                                        Belum ada peserta PPL/PML pada pelatihan ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
