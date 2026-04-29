<div class="relative flex flex-col h-full bg-white dark:bg-gray-900 rounded-2xl shadow-md border border-gray-100 dark:border-gray-800 overflow-hidden group transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
    
    <!-- Top Gradient Banner -->
    <div @class([
        'h-28 w-full absolute top-0 left-0 z-0',
        'bg-gradient-to-br from-amber-400 to-amber-600 opacity-20 dark:opacity-30' => $index == 0,
        'bg-gradient-to-br from-slate-400 to-slate-600 opacity-15 dark:opacity-25' => $index == 1,
        'bg-gradient-to-br from-orange-400 to-orange-600 opacity-15 dark:opacity-25' => $index == 2,
        'bg-gradient-to-b from-gray-50 to-transparent dark:from-gray-800/50' => $index > 2,
    ])></div>

    <!-- Rank Badge -->
    <div @class([
        'absolute top-4 right-4 px-3 py-1.5 text-xs font-black rounded-full shadow-sm z-10 flex items-center justify-center gap-1',
        'bg-gradient-to-r from-amber-300 to-amber-500 text-amber-950 ring-2 ring-white dark:ring-gray-900 shadow-amber-500/30' => $index == 0,
        'bg-gradient-to-r from-slate-200 to-slate-400 text-slate-800 ring-2 ring-white dark:ring-gray-900 shadow-slate-500/30' => $index == 1,
        'bg-gradient-to-r from-orange-300 to-orange-500 text-orange-950 ring-2 ring-white dark:ring-gray-900 shadow-orange-500/30' => $index == 2,
        'bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 ring-1 ring-gray-200 dark:ring-gray-700' => $index > 2,
    ])>
        @if($index == 0)
            <x-heroicon-s-trophy class="w-3.5 h-3.5 text-amber-800" />
        @elseif($index == 1 || $index == 2)
            <x-heroicon-s-star class="w-3.5 h-3.5 opacity-70" />
        @else
            <span class="opacity-50">#</span>
        @endif
        <span>{{ $index + 1 }}</span>
    </div>

    <!-- Card Content -->
    <div class="p-6 pt-10 flex flex-col items-center text-center flex-1 z-10">
        <!-- Avatar Section -->
        <div class="relative mb-5">
            <div @class([
                'h-24 w-24 rounded-full overflow-hidden ring-4 ring-white dark:ring-gray-900 shadow-lg bg-white transition-transform duration-500 group-hover:scale-105',
                'shadow-amber-500/40 ring-offset-2 ring-offset-amber-100 dark:ring-offset-amber-900/30' => $index == 0,
                'shadow-slate-500/30 ring-offset-2 ring-offset-slate-100 dark:ring-offset-slate-800/30' => $index == 1,
                'shadow-orange-500/30 ring-offset-2 ring-offset-orange-100 dark:ring-offset-orange-900/30' => $index == 2,
                'shadow-gray-200/50 dark:shadow-gray-900' => $index > 2,
            ])>
                @if($item->avatar)
                    <img src="{{ asset('storage/' . $item->avatar) }}" alt="{{ $item->name }}" class="h-full w-full object-cover">
                @else
                    <div class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary-50 to-primary-100 dark:from-gray-800 dark:to-gray-700 text-3xl font-black text-primary-600 dark:text-primary-400">
                        {{ $item->initials }}
                    </div>
                @endif
            </div>
            @if($index == 0)
                <div class="absolute -top-3 -right-2 text-3xl animate-bounce drop-shadow-md">👑</div>
            @endif
        </div>

        <!-- Identity -->
        <div class="space-y-1.5 flex-1 flex flex-col justify-start w-full px-2">
            <h3 class="text-base font-extrabold text-gray-900 dark:text-white leading-tight line-clamp-2">
                {{ $item->name }}
            </h3>
            <p class="text-[10px] font-bold text-primary-600 dark:text-primary-400 uppercase tracking-widest line-clamp-2 leading-relaxed">
                {{ $item->jabatan }}
            </p>
        </div>

        <!-- Task Stats -->
        <div class="w-full pt-5 mt-5 border-t border-gray-100 dark:border-gray-800/60">
            <div class="flex items-center justify-between mb-2.5">
                <span class="text-xs font-bold text-gray-500 dark:text-gray-400">Pencapaian</span>
                <div class="flex items-baseline gap-1">
                    <span class="text-3xl font-black text-gray-900 dark:text-white leading-none tracking-tight">{{ $item->count }}</span>
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Tugas</span>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="h-2 w-full bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                <div 
                    @class([
                        'h-full rounded-full transition-all duration-1000 ease-out',
                        'bg-gradient-to-r from-amber-400 to-amber-500' => $index == 0,
                        'bg-gradient-to-r from-slate-400 to-slate-500' => $index == 1,
                        'bg-gradient-to-r from-orange-400 to-orange-500' => $index == 2,
                        'bg-gradient-to-r from-primary-400 to-primary-600' => $index > 2,
                    ])
                    style="width: {{ $maxCount > 0 ? ($item->count / $maxCount) * 100 : 0 }}%"
                ></div>
            </div>
        </div>
    </div>
</div>
