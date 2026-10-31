<x-filament-panels::page>
    @php
        $data = $this->getHeatmapData();
        $daysInMonth = $data['days_in_month'];
        $users = $data['users'];
        $startOfMonth = $data['start_of_month'];
    @endphp

    {{-- Filter Header Card --}}
    <div class="p-5 bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-calendar-days class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                    Distribusi Penugasan Bulanan
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Pantau kepadatan hari bertugas petugas BPS & Mitra untuk mencegah penugasan ganda atau beban berlebih.
                </p>
            </div>

            {{-- Legend --}}
            <div class="flex flex-wrap items-center gap-3 text-xs bg-gray-50 dark:bg-gray-800/60 p-2.5 rounded-lg border border-gray-100 dark:border-gray-800">
                <span class="font-semibold text-gray-700 dark:text-gray-300">Keterangan:</span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-3.5 h-3.5 rounded bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700"></span>
                    <span class="text-gray-600 dark:text-gray-400">0 (Kosong)</span>
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-3.5 h-3.5 rounded bg-emerald-500 text-white font-bold text-[9px] flex items-center justify-center">1</span>
                    <span class="text-gray-600 dark:text-gray-400">1 Tugas (Optimal)</span>
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-3.5 h-3.5 rounded bg-amber-500 text-white font-bold text-[9px] flex items-center justify-center">2</span>
                    <span class="text-gray-600 dark:text-gray-400">2 Tugas (Padat)</span>
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-3.5 h-3.5 rounded bg-rose-600 text-white font-bold text-[9px] flex items-center justify-center">3+</span>
                    <span class="text-gray-600 dark:text-gray-400">3+ (Bentrok/Overload)</span>
                </span>
            </div>
        </div>

        {{-- Interactive Filters --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 pt-2 border-t border-gray-100 dark:border-gray-800">
            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Bulan</label>
                <select wire:model.live="selectedMonth" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    @foreach($this->getMonthsProperty() as $num => $name)
                        <option value="{{ $num }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Tahun</label>
                <select wire:model.live="selectedYear" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    @foreach($this->getYearsProperty() as $yrKey => $yrVal)
                        <option value="{{ $yrKey }}">{{ $yrVal }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Peran / Role</label>
                <select wire:model.live="selectedRole" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    <option value="all">Semua Peran</option>
                    @foreach($this->getRolesProperty() as $rKey => $rVal)
                        <option value="{{ $rKey }}">{{ $rVal }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Cari Nama / NIP</label>
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Ketik nama atau NIP..."
                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white pl-8 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-2.5 top-2.5" />
                </div>
            </div>
        </div>
    </div>

    {{-- Heatmap Matrix Table --}}
    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700">
                        <th class="p-3 font-semibold text-gray-700 dark:text-gray-200 sticky left-0 z-20 bg-gray-50 dark:bg-gray-800 min-w-[190pt] shadow-[1px_0_0_0_rgba(0,0,0,0.05)]">
                            Petugas / Pegawai
                        </th>
                        <th class="p-2 text-center font-semibold text-gray-700 dark:text-gray-200 min-w-[45pt] border-r border-gray-200 dark:border-gray-700">
                            Hari Aktif
                        </th>
                        @for($d = 1; $d <= $daysInMonth; $d++)
                            @php
                                $currentDate = $startOfMonth->copy()->day($d);
                                $isWeekend = $currentDate->isWeekend();
                            @endphp
                            <th class="p-1.5 text-center font-semibold min-w-[26pt] max-w-[26pt] border-r border-gray-100 dark:border-gray-800 {{ $isWeekend ? 'bg-rose-50/50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-400' : 'text-gray-700 dark:text-gray-300' }}">
                                <div class="text-[10pt] font-bold">{{ $d }}</div>
                                <div class="text-[8pt] uppercase font-normal opacity-75">{{ substr($currentDate->isoFormat('dd'), 0, 2) }}</div>
                            </th>
                        @endfor
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($users as $user)
                        <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/40 transition-colors">
                            {{-- Sticky User Col --}}
                            <td class="p-3 sticky left-0 z-10 bg-white dark:bg-gray-900 shadow-[1px_0_0_0_rgba(0,0,0,0.05)]">
                                <div class="font-medium text-gray-900 dark:text-gray-100 line-clamp-1" title="{{ $user['name'] }}">
                                    {{ $user['name'] }}
                                </div>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="inline-flex items-center px-1.5 py-0.2 text-[9px] rounded font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                        {{ $user['role'] }}
                                    </span>
                                    @if($user['nip'] !== '-')
                                        <span class="text-[10px] text-gray-400 font-mono">
                                            {{ $user['nip'] }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Total Active Days Col --}}
                            <td class="p-2 text-center border-r border-gray-200 dark:border-gray-700">
                                <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-[11px] font-bold {{ $user['total_active_days'] > 20 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : ($user['total_active_days'] > 0 ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/50 dark:text-primary-300' : 'text-gray-400') }}">
                                    {{ $user['total_active_days'] }} hr
                                </span>
                            </td>

                            {{-- Days Grid --}}
                            @for($d = 1; $d <= $daysInMonth; $d++)
                                @php
                                    $dayData = $user['days'][$d];
                                    $count = $dayData['count'];
                                    $currentDate = $startOfMonth->copy()->day($d);
                                    $isWeekend = $currentDate->isWeekend();

                                    $cellBg = '';
                                    if ($count === 0) {
                                        $cellBg = $isWeekend ? 'bg-gray-50/70 dark:bg-gray-800/30' : 'bg-transparent';
                                    } elseif ($count === 1) {
                                        $cellBg = 'bg-emerald-500 text-white font-bold';
                                    } elseif ($count === 2) {
                                        $cellBg = 'bg-amber-500 text-white font-bold';
                                    } else {
                                        $cellBg = 'bg-rose-600 text-white font-extrabold animate-pulse';
                                    }

                                    // Build hover tooltip title
                                    $tooltip = "Tanggal {$d} " . $this->getMonthsProperty()[$selectedMonth] . ": {$count} Tugas";
                                    if ($count > 0) {
                                        $taskList = array_map(function($t) {
                                            return "• [{$t['status']}] {$t['keperluan']} ({$t['tempat']})";
                                        }, $dayData['tasks']);
                                        $tooltip .= "\n" . implode("\n", $taskList);
                                    }
                                @endphp

                                <td class="p-1 text-center border-r border-gray-100 dark:border-gray-800/60 {{ $isWeekend && $count === 0 ? 'bg-rose-50/20 dark:bg-rose-950/10' : '' }}" title="{{ $tooltip }}">
                                    @if($count > 0)
                                        <div class="w-6 h-6 mx-auto rounded flex items-center justify-center text-[10px] {{ $cellBg }} cursor-pointer hover:scale-110 transition-transform shadow-xs">
                                            {{ $count }}
                                        </div>
                                    @else
                                        <span class="text-gray-200 dark:text-gray-800 select-none">•</span>
                                    @endif
                                </td>
                            @endfor
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $daysInMonth + 2 }}" class="p-8 text-center text-gray-500 dark:text-gray-400">
                                Tidak ada data petugas atau penugasan yang cocok dengan filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
