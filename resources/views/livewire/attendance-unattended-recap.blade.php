<div class="space-y-4" x-data="{ copied: false }">
    {{-- TOP FILTER ROW --}}
    <div class="p-3.5 bg-gray-50 border border-gray-200 rounded-xl dark:bg-gray-900/60 dark:border-gray-800 space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
            {{-- Pilih Kegiatan --}}
            <div class="sm:col-span-8">
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Kegiatan / Survei
                </label>
                <select 
                    wire:model.live="surveyId" 
                    class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 font-medium"
                >
                    @foreach($activeSurveys as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Tanggal --}}
            <div class="sm:col-span-4">
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Tanggal Pemantauan
                </label>
                <input 
                    type="date" 
                    wire:model.live="date" 
                    class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                />
            </div>
        </div>

        {{-- STATUS TABS & COPY ACTION --}}
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 border-t border-gray-200 dark:border-gray-800">
            {{-- Tabs --}}
            <div class="flex items-center gap-1.5 w-full sm:w-auto">
                <button 
                    type="button"
                    wire:click="$set('activeTab', 'all')"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $activeTab === 'all' ? 'bg-primary-600 text-white shadow-sm' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-100 border border-gray-200 dark:border-gray-700' }}"
                >
                    Semua ({{ $recap['total_petugas'] }})
                </button>

                <button 
                    type="button"
                    wire:click="$set('activeTab', 'sudah')"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $activeTab === 'sudah' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 border border-emerald-200 dark:border-emerald-800' }}"
                >
                    Sudah Presensi ({{ $recap['sudah_count'] }})
                </button>

                <button 
                    type="button"
                    wire:click="$set('activeTab', 'belum')"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $activeTab === 'belum' ? 'bg-rose-600 text-white shadow-sm' : 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 hover:bg-rose-100 border border-rose-200 dark:border-rose-800' }}"
                >
                    Belum Presensi ({{ $recap['belum_count'] }})
                </button>

                @if($recap['cuti_count'] > 0)
                    <span class="text-xs text-amber-600 dark:text-amber-400 font-medium ml-1">
                        ({{ $recap['cuti_count'] }} Cuti)
                    </span>
                @endif
            </div>

            {{-- Action Salin ke WA --}}
            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                <textarea id="copy-target-wa-text" class="hidden">{{ $recap['copy_text'] }}</textarea>
                <button 
                    type="button"
                    @click="
                        navigator.clipboard.writeText(document.getElementById('copy-target-wa-text').value);
                        copied = true;
                        setTimeout(() => copied = false, 2500);
                    "
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-emerald-800 bg-emerald-100 hover:bg-emerald-200 rounded-lg dark:bg-emerald-900/60 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-800 transition-colors shadow-sm"
                >
                    <template x-if="!copied">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path>
                            </svg>
                            Salin Format WA Group
                        </span>
                    </template>
                    <template x-if="copied">
                        <span class="inline-flex items-center gap-1.5 font-bold text-emerald-900 dark:text-emerald-100">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Tersalin ke Clipboard!
                        </span>
                    </template>
                </button>
            </div>
        </div>
    </div>

    {{-- SEARCH BAR --}}
    <div class="relative w-full">
        <input 
            type="text" 
            wire:model.live.debounce.250ms="search" 
            placeholder="Cari nama petugas / nomor HP..." 
            class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white pl-8 pr-3 py-2 shadow-sm focus:border-primary-500 focus:ring-primary-500"
        />
        <svg class="w-4 h-4 absolute left-2.5 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
    </div>

    {{-- TABLE DAFTAR PETUGAS --}}
    <div class="overflow-x-auto bg-white border border-gray-200 rounded-xl dark:bg-gray-800 dark:border-gray-700 shadow-sm max-h-[460px] overflow-y-auto">
        <table class="w-full text-left border-collapse text-sm">
            <thead class="sticky top-0 z-10">
                <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                    <th class="py-2.5 px-3 w-10 text-center">No</th>
                    <th class="py-2.5 px-3">Nama Petugas</th>
                    <th class="py-2.5 px-3 text-center">Status</th>
                    <th class="py-2.5 px-3 text-center">Jam Datang</th>
                    <th class="py-2.5 px-3">No. WhatsApp</th>
                    <th class="py-2.5 px-3 text-center w-28">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                @forelse($recap['items'] as $index => $item)
                    <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/30 transition-colors {{ $item['status'] === 'belum' ? 'bg-rose-50/40 dark:bg-rose-950/20' : '' }}">
                        <td class="py-2.5 px-3 text-center text-xs text-gray-500 dark:text-gray-400">
                            {{ $index + 1 }}
                        </td>

                        {{-- Nama --}}
                        <td class="py-2.5 px-3 font-medium text-gray-900 dark:text-white">
                            {{ $item['name'] }}
                        </td>

                        {{-- Status --}}
                        <td class="py-2.5 px-3 text-center">
                            @if($item['status'] === 'sudah')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                    <svg class="w-3 h-3 mr-1 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Sudah
                                </span>
                            @elseif($item['status'] === 'cuti')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-800">
                                    Cuti
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-300 dark:border-rose-800">
                                    <span class="w-1.5 h-1.5 mr-1 bg-rose-500 rounded-full animate-pulse"></span>
                                    Belum
                                </span>
                            @endif
                        </td>

                        {{-- Jam Datang --}}
                        <td class="py-2.5 px-3 text-center text-xs font-mono text-gray-700 dark:text-gray-300">
                            @if($item['check_in_time'])
                                <span class="font-semibold text-emerald-700 dark:text-emerald-300">{{ $item['check_in_time'] }}</span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>

                        {{-- No HP --}}
                        <td class="py-2.5 px-3 text-xs font-mono text-gray-600 dark:text-gray-300">
                            @if(!empty($item['phone']))
                                {{ $item['phone'] }}
                            @else
                                <span class="text-gray-400 italic">Belum ada</span>
                            @endif
                        </td>

                        {{-- Aksi Ingatkan --}}
                        <td class="py-2.5 px-3 text-center">
                            @if($item['status'] === 'belum')
                                @if(!empty($item['wa_url']))
                                    <a 
                                        href="{{ $item['wa_url'] }}" 
                                        target="_blank" 
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-md shadow-sm transition-transform active:scale-95"
                                        title="Kirim pesan pengingat WA"
                                    >
                                        <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                            <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.099.824zm-3.423-14.416c-6.627 0-12 5.373-12 12 0 2.155.57 4.178 1.564 5.925l-1.657 6.059 6.22-1.632c1.705.931 3.659 1.468 5.873 1.468 6.627 0 12-5.373 12-12s-5.373-12-12-12zm0 22c-1.897 0-3.666-.543-5.161-1.48l-.37-.232-3.834 1.006 1.024-3.742-.254-.404c-1.077-1.713-1.655-3.699-1.655-5.768 0-5.748 4.673-10.22 10.25-10.22 5.776 0 10.25 4.472 10.25 10.22 0 5.748-4.474 10.22-10.25 10.22z"/>
                                        </svg>
                                        Ingatkan
                                    </a>
                                @else
                                    <span class="text-xs text-gray-400 italic">-</span>
                                @endif
                            @else
                                <span class="text-xs text-emerald-600 font-medium">✓ Selesai</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-xs text-gray-500 dark:text-gray-400">
                            Tidak ada data petugas pada filter ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
