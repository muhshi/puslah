@php
    $reports = \App\Models\SharedWorkReport::where('user_id', auth()->id())
        ->latest()
        ->get();
@endphp

<div class="space-y-4" x-data="{ copiedId: null }">
    @if($reports->isEmpty())
        <div class="p-8 text-center bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-dashed border-gray-200 dark:border-gray-700">
            <div class="text-3xl mb-2">📂</div>
            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-200">Belum Ada Berkas Publik</h4>
            <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">
                Centang satu atau beberapa kegiatan pada tabel di bawah, lalu pilih menu aksi massal <strong>"🔗 Buat Berkas Publik Multi-Hari"</strong> untuk membuat tautan resmi E-Kinerja.
            </p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full text-left text-xs text-gray-700 dark:text-gray-300 divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400 font-bold uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="px-4 py-3">Judul Berkas</th>
                        <th class="px-3 py-3">Kegiatan</th>
                        <th class="px-3 py-3">Dilihat</th>
                        <th class="px-3 py-3">Dibuat</th>
                        <th class="px-4 py-3 text-right">Aksi & Tautan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900 font-medium">
                    @foreach($reports as $rep)
                        @php
                            $shareUrl = route('shared-work-report.show', ['uuid' => $rep->uuid]);
                            $actCount = is_array($rep->activity_ids) ? count($rep->activity_ids) : 0;
                        @endphp
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="font-bold text-gray-900 dark:text-white">{{ $rep->title }}</div>
                                @if($rep->description)
                                    <div class="text-[11px] text-gray-400 truncate max-w-xs">{{ $rep->description }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                                    {{ $actCount }} Hari
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">
                                {{ $rep->view_count }}x
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-400">
                                {{ $rep->created_at->translatedFormat('d M Y') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right space-x-1">
                                <button
                                    type="button"
                                    @click="navigator.clipboard.writeText('{{ $shareUrl }}'); copiedId = {{ $rep->id }}; setTimeout(() => copiedId = null, 2500)"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold rounded-md bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 transition-colors"
                                >
                                    <span x-text="copiedId === {{ $rep->id }} ? 'Tersalin! ✅' : '📋 Salin Link'"></span>
                                </button>

                                <a
                                    href="{{ $shareUrl }}"
                                    target="_blank"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-semibold rounded-md bg-sky-50 text-sky-700 hover:bg-sky-100 dark:bg-sky-950 dark:text-sky-300 border border-sky-200 dark:border-sky-800 transition-colors"
                                >
                                    🌐 Buka
                                </a>

                                <a
                                    href="{{ route('shared-work-report.download', ['uuid' => $rep->uuid]) }}"
                                    class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-semibold rounded-md bg-gray-50 text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 border border-gray-200 dark:border-gray-700 transition-colors"
                                >
                                    📥 Word
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
