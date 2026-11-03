@php
    $reports = \App\Models\SharedWorkReport::where('user_id', auth()->id())
        ->latest()
        ->get();
@endphp

<div
    class="space-y-4 text-slate-800 dark:text-slate-100"
    x-data="{
        showForm: false,
        isEditing: false,
        copiedId: null,
        activeMenuId: null,
        formData: {
            id: null,
            title: '',
            tag_slug: '',
            period_start: '',
            period_end: '',
            description: ''
        },
        isSaving: false,
        showAiModal: false,
        isGeneratingAi: false,
        aiSummaryTitle: '',
        aiSummaryText: '',
        copiedAi: false,
        openAiSummary(id, title) {
            this.aiSummaryTitle = title;
            this.aiSummaryText = '';
            this.isGeneratingAi = true;
            this.showAiModal = true;
            this.copiedAi = false;

            fetch('/bukti-dukung/wadah/' + id + '/ai-summary', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            })
            .then(async res => {
                const data = await res.json();
                if (res.ok && data.success) {
                    this.aiSummaryText = data.summary;
                } else {
                    this.aiSummaryText = data.message || 'Gagal menghasilkan ringkasan AI.';
                }
            })
            .catch(err => {
                this.aiSummaryText = 'Terjadi kesalahan jaringan: ' + err.message;
            })
            .finally(() => {
                this.isGeneratingAi = false;
            });
        },
        saveWadah() {
            if (!this.formData.title || !this.formData.title.trim()) {
                alert('Nama Butir Kegiatan KIPAPP wajib diisi!');
                return;
            }
            this.isSaving = true;
            const url = this.isEditing 
                ? '/bukti-dukung/wadah/' + this.formData.id + '/update' 
                : '{{ route("shared-work-report.store") }}';
            
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(this.formData)
            })
            .then(async res => {
                if (res.ok) {
                    window.location.reload();
                } else {
                    const data = await res.json().catch(() => ({}));
                    alert(data.message || 'Gagal menyimpan data wadah.');
                    this.isSaving = false;
                }
            })
            .catch(err => {
                alert('Terjadi kesalahan jaringan: ' + err.message);
                this.isSaving = false;
            });
        },
        deleteWadah(id) {
            if (!confirm('Apakah Anda yakin ingin menghapus wadah KIPAPP ini? Link publik akan dinonaktifkan.')) {
                return;
            }
            fetch('/bukti-dukung/wadah/' + id + '/delete', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            })
            .then(res => {
                if (res.ok) {
                    window.location.reload();
                } else {
                    alert('Gagal menghapus wadah.');
                }
            })
            .catch(err => alert('Error: ' + err.message));
        },
        openCreate() {
            this.isEditing = false;
            this.formData = { id: null, title: '', tag_slug: '', period_start: '', period_end: '', description: '' };
            this.showForm = true;
        },
        openEdit(item) {
            this.isEditing = true;
            this.formData = {
                id: item.id,
                title: item.title,
                tag_slug: item.tag_slug || '',
                period_start: item.period_start ? item.period_start.substring(0, 10) : '',
                period_end: item.period_end ? item.period_end.substring(0, 10) : '',
                description: item.description || ''
            };
            this.showForm = true;
            this.activeMenuId = null;
        },
        cancelForm() {
            this.showForm = false;
            this.isEditing = false;
        }
    }"
    @click.away="activeMenuId = null"
>
    {{-- Header Banner & Tombol Tambah Wadah Baru --}}
    <div class="flex flex-wrap items-center justify-between gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800">
        <div>
            <h4 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span>📁</span> Wadah Butir Kegiatan KIPAPP / E-Kinerja
            </h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Setiap wadah memiliki 1 link publik permanen untuk disematkan di KIPAPP.
            </p>
        </div>

        {{-- Tombol Tambah Wadah Baru (Menggunakan Komponen Native Filament / Tailwind) --}}
        <x-filament::button
            type="button"
            color="primary"
            icon="heroicon-m-plus"
            size="sm"
            @click="openCreate()"
        >
            Buat Wadah Baru
        </x-filament::button>
    </div>

    {{-- Form Inline Tambah / Ubah Wadah --}}
    <div
        x-show="showForm"
        x-cloak
        x-transition
        class="p-4 rounded-xl border border-sky-300 dark:border-sky-800 bg-sky-50/70 dark:bg-sky-950/30 shadow-md space-y-3"
    >
        <div class="flex items-center justify-between border-b border-sky-200 dark:border-sky-900/50 pb-2">
            <h5 class="text-xs font-bold text-sky-900 dark:text-sky-200 flex items-center gap-1.5" x-text="isEditing ? '✏️ Ubah Wadah KIPAPP' : '➕ Buat Wadah Butir KIPAPP Baru'"></h5>
            <button type="button" @click="cancelForm()" class="text-xs font-bold text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 cursor-pointer">Batal ✕</button>
        </div>

        <div class="space-y-3 text-xs">
            <div>
                <label class="block font-bold text-slate-800 dark:text-slate-200 mb-1">Nama Butir Kegiatan KIPAPP *</label>
                <input
                    type="text"
                    name="title"
                    x-model="formData.title"
                    required
                    placeholder="Contoh: Pengolahan dan Pemutakhiran Peta Wilkerstat"
                    class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:outline-none"
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block font-bold text-slate-800 dark:text-slate-200 mb-1">Hashtag Telegram (#bot)</label>
                    <input
                        type="text"
                        name="tag_slug"
                        x-model="formData.tag_slug"
                        placeholder="contoh: wilkerstat"
                        class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:outline-none"
                    />
                    <span class="text-[10px] text-slate-500 mt-0.5 block">Ketik di bot: #wilkerstat</span>
                </div>
                <div>
                    <label class="block font-bold text-slate-800 dark:text-slate-200 mb-1">Periode Mulai (Opsional)</label>
                    <input
                        type="date"
                        name="period_start"
                        x-model="formData.period_start"
                        class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:outline-none"
                    />
                </div>
                <div>
                    <label class="block font-bold text-slate-800 dark:text-slate-200 mb-1">Periode Selesai (Opsional)</label>
                    <input
                        type="date"
                        name="period_end"
                        x-model="formData.period_end"
                        class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:outline-none"
                    />
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-800 dark:text-slate-200 mb-1">Catatan / Keterangan (Opsional)</label>
                <textarea
                    name="description"
                    x-model="formData.description"
                    rows="2"
                    placeholder="Uraian ringkas atau catatan butir SKP..."
                    class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:ring-2 focus:ring-sky-500 focus:outline-none"
                ></textarea>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-sky-200/60 dark:border-sky-900/40">
                <x-filament::button
                    type="button"
                    color="gray"
                    size="sm"
                    @click="cancelForm()"
                >
                    Batal
                </x-filament::button>
                <x-filament::button
                    type="button"
                    color="primary"
                    size="sm"
                    icon="heroicon-m-check"
                    @click="saveWadah()"
                    x-bind:disabled="isSaving"
                >
                    <span x-text="isSaving ? 'Menyimpan...' : (isEditing ? 'Simpan Perubahan' : 'Buat Wadah KIPAPP')">Buat Wadah KIPAPP</span>
                </x-filament::button>
            </div>
        </div>
    </div>

    {{-- Tabel Wadah KIPAPP --}}
    @if($reports->isEmpty())
        <div class="p-8 text-center bg-white dark:bg-slate-800 rounded-xl border border-dashed border-slate-300 dark:border-slate-700">
            <div class="text-3xl mb-2">📂</div>
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Belum Ada Wadah Butir KIPAPP</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                Klik tombol <strong>"+ Buat Wadah Baru"</strong> di atas untuk membuat wadah bukti dukung pertama Anda (misal: Wilkerstat, Pengembangan Inovasi, dsb).
            </p>
        </div>
    @else
        <div class="overflow-visible rounded-xl border border-slate-200 dark:border-slate-700 shadow-xs">
            <table class="w-full text-left text-xs text-slate-700 dark:text-slate-200 divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="px-4 py-3">Butir Kegiatan KIPAPP</th>
                        <th class="px-3 py-3 text-center">Kegiatan</th>
                        <th class="px-3 py-3 text-center">Dilihat</th>
                        <th class="px-3 py-3">Dibuat</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900 font-medium">
                    @foreach($reports as $rep)
                        @php
                            $shareUrl = route('shared-work-report.show', ['uuid' => $rep->uuid]);
                            $actCount = $rep->activities_count;
                        @endphp
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/50 transition-colors">
                            {{-- Judul & Hashtag (Deskripsi langsung di bawah judul) --}}
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-900 dark:text-white text-[13px]">
                                    {{ $rep->title }}
                                </div>
                                <div class="text-[11px] text-sky-600 dark:text-sky-400 font-mono mt-0.5 flex items-center gap-1.5 flex-wrap">
                                    <span class="font-bold">#{{ $rep->tag_slug ?: 'kegiatan' }}</span>
                                    @if($rep->description)
                                        <span class="text-slate-400 font-sans">&bull; {{ $rep->description }}</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Jumlah Hari Kegiatan --}}
                            <td class="px-3 py-3 text-center whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                    {{ $actCount }} Hari
                                </span>
                            </td>

                            {{-- Hit Counter --}}
                            <td class="px-3 py-3 text-center whitespace-nowrap text-slate-500 dark:text-slate-400">
                                {{ $rep->view_count }}x
                            </td>

                            {{-- Tanggal --}}
                            <td class="px-3 py-3 whitespace-nowrap text-slate-400 text-[11px]">
                                {{ $rep->created_at->translatedFormat('d M Y') }}
                            </td>

                            {{-- Aksi Menu Titik 3 (Kebab Dropdown) --}}
                            <td class="px-4 py-3 whitespace-nowrap text-right relative">
                                <div class="inline-block text-left" x-data="{ open: false }">
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        @click.away="open = false"
                                        class="p-1.5 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800 transition-colors focus:outline-none focus:ring-2 focus:ring-sky-500 cursor-pointer"
                                        title="Pilihan Aksi"
                                    >
                                        {{-- Ikon Titik Tiga (Kebab Menu) --}}
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                        </svg>
                                    </button>

                                    {{-- Popover Dropdown Menu --}}
                                    <div
                                        x-show="open"
                                        x-cloak
                                        x-transition:enter="transition ease-out duration-100"
                                        x-transition:enter-start="transform opacity-0 scale-95"
                                        x-transition:enter-end="transform opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-75"
                                        x-transition:leave-start="transform opacity-100 scale-100"
                                        x-transition:leave-end="transform opacity-0 scale-95"
                                        class="absolute right-0 mt-1 w-48 rounded-xl shadow-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 py-1.5 z-50 text-left text-xs"
                                    >
                                        {{-- 1. Salin Link KIPAPP --}}
                                        <button
                                            type="button"
                                            @click="navigator.clipboard.writeText('{{ $shareUrl }}'); copiedId = {{ $rep->id }}; open = false; setTimeout(() => copiedId = null, 2500)"
                                            class="w-full px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-700/60 flex items-center gap-2 text-slate-700 dark:text-slate-200 transition-colors cursor-pointer"
                                        >
                                            <span class="text-emerald-600 font-bold">📋</span>
                                            <span x-text="copiedId === {{ $rep->id }} ? 'Tersalin! ✅' : 'Salin Link KIPAPP'">Salin Link KIPAPP</span>
                                        </button>

                                        {{-- 2. Buka Halaman Publik --}}
                                        <a
                                            href="{{ $shareUrl }}"
                                            target="_blank"
                                            @click="open = false"
                                            class="w-full px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-700/60 flex items-center gap-2 text-slate-700 dark:text-slate-200 transition-colors cursor-pointer"
                                        >
                                            <span class="text-sky-600 font-bold">🌐</span>
                                            <span>Buka Halaman Publik</span>
                                        </a>

                                        {{-- 3. Unduh Word --}}
                                        <a
                                            href="{{ route('shared-work-report.download', ['uuid' => $rep->uuid]) }}"
                                            @click="open = false"
                                            class="w-full px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-700/60 flex items-center gap-2 text-slate-700 dark:text-slate-200 transition-colors cursor-pointer"
                                        >
                                            <span class="text-blue-600 font-bold">📥</span>
                                            <span>Unduh Dokumen Word</span>
                                        </a>

                                        {{-- 3b. Unduh Buku PDF --}}
                                        <a
                                            href="{{ route('shared-work-report.download-pdf', ['uuid' => $rep->uuid]) }}"
                                            @click="open = false"
                                            class="w-full px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-700/60 flex items-center gap-2 text-slate-700 dark:text-slate-200 transition-colors cursor-pointer"
                                        >
                                            <span class="text-rose-600 font-bold">📄</span>
                                            <span>Unduh Buku PDF</span>
                                        </a>

                                        {{-- 3c. Ringkasan Realisasi SKP (AI) --}}
                                        <button
                                            type="button"
                                            @click="openAiSummary({{ $rep->id }}, '{{ addslashes($rep->title) }}'); open = false;"
                                            class="w-full px-3 py-2 text-left hover:bg-purple-50 dark:hover:bg-purple-950/40 flex items-center gap-2 text-purple-700 dark:text-purple-300 font-medium transition-colors cursor-pointer"
                                        >
                                            <span>✨</span>
                                            <span>Ringkasan SKP (AI)</span>
                                        </button>

                                        <div class="border-t border-slate-100 dark:border-slate-700 my-1"></div>

                                        {{-- 4. Ubah / Edit --}}
                                        <button
                                            type="button"
                                            @click="openEdit({{ json_encode($rep) }}); open = false;"
                                            class="w-full px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-700/60 flex items-center gap-2 text-amber-600 dark:text-amber-400 transition-colors cursor-pointer"
                                        >
                                            <span>✏️</span>
                                            <span>Ubah Wadah Ini</span>
                                        </button>

                                        {{-- 5. Hapus --}}
                                        <button
                                            type="button"
                                            @click="deleteWadah({{ $rep->id }}); open = false;"
                                            class="w-full px-3 py-2 text-left hover:bg-red-50 dark:hover:bg-red-950/40 flex items-center gap-2 text-red-600 dark:text-red-400 transition-colors cursor-pointer"
                                        >
                                            <span>🗑️</span>
                                            <span>Hapus Wadah</span>
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Pop-up Modal Ringkasan SKP (Gemini AI) --}}
    <div
        x-show="showAiModal"
        x-cloak
        x-transition
        class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
    >
        <div
            @click.away="showAiModal = false"
            class="w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-5 space-y-4 text-xs"
        >
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                <div>
                    <h4 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>✨</span> Ringkasan Capaian SKP (Gemini AI)
                    </h4>
                    <p class="text-[11px] text-sky-600 dark:text-sky-400 font-semibold mt-0.5" x-text="aiSummaryTitle"></p>
                </div>
                <button
                    type="button"
                    @click="showAiModal = false"
                    class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                >
                    ✕
                </button>
            </div>

            {{-- Loading State --}}
            <div x-show="isGeneratingAi" class="py-8 text-center space-y-3">
                <div class="inline-block animate-spin text-2xl">⏳</div>
                <p class="text-slate-500 font-medium">Gemini AI sedang membaca seluruh log kegiatan dan menyintesis narasi capaian kinerja...</p>
            </div>

            {{-- Result State --}}
            <div x-show="!isGeneratingAi" class="space-y-3">
                <label class="block font-semibold text-slate-700 dark:text-slate-300">
                    Uraian Realisasi (Siap Disalin ke Kotak Realisasi E-Kinerja):
                </label>
                <div
                    class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/70 border border-slate-200 dark:border-slate-700 font-sans text-slate-800 dark:text-slate-100 leading-relaxed max-h-60 overflow-y-auto select-all"
                    x-text="aiSummaryText"
                ></div>

                <div class="flex items-center justify-between pt-2">
                    <span class="text-[10px] text-slate-400">💡 Anda dapat langsung menyalin teks di atas ke formulir E-Kinerja BPS.</span>
                    <div class="flex items-center gap-2">
                        <x-filament::button
                            type="button"
                            color="gray"
                            size="sm"
                            @click="showAiModal = false"
                        >
                            Tutup
                        </x-filament::button>
                        <x-filament::button
                            type="button"
                            color="primary"
                            size="sm"
                            icon="heroicon-m-clipboard-document-check"
                            @click="navigator.clipboard.writeText(aiSummaryText); copiedAi = true; setTimeout(() => copiedAi = false, 2500)"
                        >
                            <span x-text="copiedAi ? 'Tersalin! ✅' : 'Salin Ringkasan 📋'">Salin Ringkasan</span>
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
