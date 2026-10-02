<div class="space-y-6" x-data="{ copied: false }">
    {{-- STATS CARDS --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="p-4 bg-white border border-gray-200 rounded-xl dark:bg-gray-800 dark:border-gray-700 shadow-sm">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Wajib Presensi</div>
            <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $recap['total_target'] }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Pegawai/Petugas</div>
        </div>

        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl dark:bg-emerald-950/40 dark:border-emerald-800 shadow-sm">
            <div class="text-xs font-medium text-emerald-700 dark:text-emerald-400">Sudah Hadir (Presensi)</div>
            <div class="mt-1 text-2xl font-bold text-emerald-800 dark:text-emerald-300">{{ $recap['attended_count'] }}</div>
            <div class="text-xs text-emerald-600 dark:text-emerald-400 mt-0.5">
                {{ $recap['total_target'] > 0 ? round(($recap['attended_count'] / $recap['total_target']) * 100) : 0 }}% Kehadiran
            </div>
        </div>

        <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl dark:bg-rose-950/40 dark:border-rose-800 shadow-sm">
            <div class="text-xs font-medium text-rose-700 dark:text-rose-400">Belum Presensi</div>
            <div class="mt-1 text-2xl font-bold text-rose-800 dark:text-rose-300">{{ $recap['unattended_count'] }}</div>
            <div class="text-xs text-rose-600 dark:text-rose-400 mt-0.5">Perlu Diingatkan</div>
        </div>

        <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl dark:bg-amber-950/40 dark:border-amber-800 shadow-sm">
            <div class="text-xs font-medium text-amber-700 dark:text-amber-400">Sedang Cuti / Izin</div>
            <div class="mt-1 text-2xl font-bold text-amber-800 dark:text-amber-300">{{ $recap['leave_count'] }}</div>
            <div class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">Status Approved</div>
        </div>
    </div>

    {{-- FILTER BAR --}}
    <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl dark:bg-gray-900/60 dark:border-gray-800 space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            {{-- Tanggal --}}
            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Tanggal</label>
                <input 
                    type="date" 
                    wire:model.live="date" 
                    class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                />
            </div>

            {{-- Kegiatan Aktif --}}
            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Kegiatan Aktif</label>
                <select 
                    wire:model.live="surveyId" 
                    class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="">-- Semua Kegiatan Aktif & Pegawai --</option>
                    @foreach($activeSurveys as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Kategori Pegawai --}}
            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Kategori Pegawai</label>
                <select 
                    wire:model.live="userType" 
                    class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="all">Semua Kategori</option>
                    <option value="organik">Organik (PNS / PPPK)</option>
                    <option value="mitra">Mitra Statistik</option>
                </select>
            </div>

            {{-- Filter Status --}}
            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Status Tampilan</label>
                <select 
                    wire:model.live="statusFilter" 
                    class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="unattended">Belum Presensi Saja (Tanpa Cuti)</option>
                    <option value="all">Semua (Termasuk yang Cuti)</option>
                    <option value="leave">Hanya Sedang Cuti / Izin</option>
                </select>
            </div>
        </div>

        {{-- SEARCH & ACTIONS ROW --}}
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 border-t border-gray-200 dark:border-gray-800">
            <div class="relative w-full sm:w-72">
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Cari nama pegawai / no HP..." 
                    class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white pl-8 pr-3 py-2 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                />
                <svg class="w-4 h-4 absolute left-2.5 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                {{-- Copy WA Group Text Button --}}
                <textarea id="copy-target-wa-text" class="hidden">{{ $recap['copy_text'] }}</textarea>
                <button 
                    type="button"
                    @click="
                        navigator.clipboard.writeText(document.getElementById('copy-target-wa-text').value);
                        copied = true;
                        setTimeout(() => copied = false, 2500);
                    "
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-emerald-700 bg-emerald-100 hover:bg-emerald-200 rounded-lg dark:bg-emerald-900/60 dark:text-emerald-300 dark:hover:bg-emerald-900 transition-colors shadow-sm"
                >
                    <template x-if="!copied">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path>
                            </svg>
                            Salin Format WA Group
                        </span>
                    </template>
                    <template x-if="copied">
                        <span class="inline-flex items-center gap-1.5 text-emerald-800 dark:text-emerald-200 font-bold">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Tersalin ke Clipboard!
                        </span>
                    </template>
                </button>

                {{-- Export CSV --}}
                <button 
                    type="button"
                    wire:click="exportCsv"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-700 transition-colors shadow-sm"
                >
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Export CSV
                </button>
            </div>
        </div>
    </div>

    {{-- LIST TABLE --}}
    <div class="overflow-x-auto bg-white border border-gray-200 rounded-xl dark:bg-gray-800 dark:border-gray-700 shadow-sm">
        <table class="w-full text-left border-collapse text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/60 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                    <th class="py-3 px-4 w-12 text-center">No</th>
                    <th class="py-3 px-4">Pegawai / Petugas</th>
                    <th class="py-3 px-4">Jabatan / Role</th>
                    <th class="py-3 px-4">Kegiatan Aktif</th>
                    <th class="py-3 px-4">Kontak (HP)</th>
                    <th class="py-3 px-4 text-center">Status</th>
                    <th class="py-3 px-4 text-center">Aksi Pengingat</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                @forelse($recap['items'] as $index => $item)
                    <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-700/40 transition-colors">
                        <td class="py-3 px-4 text-center text-xs text-gray-500 dark:text-gray-400">
                            {{ $index + 1 }}
                        </td>

                        {{-- Pegawai Info --}}
                        <td class="py-3 px-4">
                            <div class="font-medium text-gray-900 dark:text-white">
                                {{ $item['name'] }}
                            </div>
                            @if(!empty($item['email']))
                                <div class="text-xs text-gray-400 dark:text-gray-500">
                                    {{ $item['email'] }}
                                </div>
                            @endif
                        </td>

                        {{-- Jabatan / Role --}}
                        <td class="py-3 px-4 text-xs text-gray-600 dark:text-gray-300">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                {{ $item['jabatan'] ?: 'Pegawai' }}
                            </span>
                        </td>

                        {{-- Kegiatan Aktif --}}
                        <td class="py-3 px-4 text-xs text-gray-600 dark:text-gray-300 max-w-xs">
                            @if(count($item['active_surveys']) > 0)
                                <div class="flex flex-wrap gap-1">
                                    @foreach($item['active_surveys'] as $actSurvey)
                                        <span class="inline-block px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800 text-[11px] truncate max-w-[220px]" title="{{ $actSurvey }}">
                                            {{ $actSurvey }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-gray-400 text-xs italic">Umum / Organik</span>
                            @endif
                        </td>

                        {{-- HP --}}
                        <td class="py-3 px-4 text-xs font-mono text-gray-600 dark:text-gray-300">
                            @if(!empty($item['phone']))
                                {{ $item['phone'] }}
                            @else
                                <span class="text-gray-400 italic">Belum diisi</span>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="py-3 px-4 text-center">
                            @if($item['is_leave'])
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300">
                                    <span class="w-1.5 h-1.5 mr-1.5 bg-amber-500 rounded-full"></span>
                                    Sedang Cuti
                                </span>
                                @if(!empty($item['leave_reason']))
                                    <div class="text-[10px] text-gray-500 mt-0.5">({{ $item['leave_reason'] }})</div>
                                @endif
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300">
                                    <span class="w-1.5 h-1.5 mr-1.5 bg-rose-500 rounded-full animate-pulse"></span>
                                    Belum Presensi
                                </span>
                            @endif
                        </td>

                        {{-- Action Ingatkan WA --}}
                        <td class="py-3 px-4 text-center">
                            @if(!empty($item['wa_url']))
                                <a 
                                    href="{{ $item['wa_url'] }}" 
                                    target="_blank" 
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-sm transition-transform active:scale-95"
                                    title="Kirim pesan pengingat ke WhatsApp {{ $item['name'] }}"
                                >
                                    {{-- WhatsApp SVG icon --}}
                                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                        <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824zm-3.423-14.416c-6.627 0-12 5.373-12 12 0 2.155.57 4.178 1.564 5.925l-1.657 6.059 6.22-1.632c1.705.931 3.659 1.468 5.873 1.468 6.627 0 12-5.373 12-12s-5.373-12-12-12zm0 22c-1.897 0-3.666-.543-5.161-1.48l-.37-.232-3.834 1.006 1.024-3.742-.254-.404c-1.077-1.713-1.655-3.699-1.655-5.768 0-5.748 4.673-10.22 10.25-10.22 5.776 0 10.25 4.472 10.25 10.22 0 5.748-4.474 10.22-10.25 10.22z"/>
                                    </svg>
                                    Ingatkan
                                </a>
                            @else
                                <span class="text-xs text-gray-400 italic">No WA tidak ada</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center">
                            <div class="max-w-sm mx-auto space-y-2">
                                <div class="w-12 h-12 mx-auto rounded-full bg-emerald-100 dark:bg-emerald-950 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <div class="text-base font-semibold text-gray-900 dark:text-white">
                                    Semua Sudah Presensi!
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Seluruh target pegawai / petugas yang difilter sudah melakukan presensi atau tercatat sedang cuti.
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(count($recap['items']) > 0)
        <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center justify-between">
            <span>Menampilkan {{ count($recap['items']) }} pegawai/petugas.</span>
            <span>Gunakan tombol "Salin Format WA Group" untuk mengirimkan pengingat kolektif.</span>
        </div>
    @endif
</div>
