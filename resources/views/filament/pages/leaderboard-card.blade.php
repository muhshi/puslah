<div class="relative flex flex-col h-full bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden group transition-all duration-300 hover:shadow-md hover:border-primary-500/30">
    
    <!-- Rank Indicator -->
    <div @class([
        'absolute top-0 right-0 px-3 py-1 text-[9px] font-black uppercase tracking-widest rounded-bl-xl shadow-sm z-10',
        'bg-amber-400 text-amber-950' => $index == 0,
        'bg-slate-300 text-slate-800' => $index == 1,
        'bg-orange-400 text-orange-950' => $index == 2,
        'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => $index > 2,
    ])>
        Rank #{{ $index + 1 }}
    </div>

    <!-- Card Content -->
    <div class="p-6 flex flex-col items-center text-center flex-1">
        <!-- Avatar Section -->
        <div class="relative mb-4 mt-2">
            <div @class([
                'h-20 w-20 rounded-full overflow-hidden ring-4 transition-transform duration-500 group-hover:scale-105',
                'ring-amber-400/20' => $index == 0,
                'ring-slate-300/20' => $index == 1,
                'ring-orange-400/20' => $index == 2,
                'ring-gray-100 dark:ring-gray-800' => $index > 2,
            ])>
                @if($item->avatar)
                    <img src="{{ asset('storage/' . $item->avatar) }}" alt="{{ $item->name }}" class="h-full w-full object-cover">
                @else
                    <div class="flex h-full w-full items-center justify-center bg-gray-50 dark:bg-gray-800 text-xl font-black text-primary-600 dark:text-primary-400">
                        {{ $item->initials }}
                    </div>
                @endif
            </div>
            @if($index == 0)
                <div class="absolute -top-2 -right-1 text-2xl animate-bounce">👑</div>
            @endif
        </div>

        <!-- Identity -->
        <div class="space-y-1 mb-4 flex-1 flex flex-col justify-center w-full">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white leading-snug line-clamp-2 min-h-[2.5rem] flex items-center justify-center px-1">
                {{ $item->name }}
            </h3>
            <p class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest truncate w-full px-2">
                {{ $item->jabatan }}
            </p>
        </div>

        <!-- Task Stats -->
        <div class="w-full pt-4 border-t border-gray-100 dark:border-gray-800">
            <div class="flex items-end justify-between mb-1.5 px-1">
                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-tight">Capaian</span>
                <div class="flex items-baseline gap-0.5">
                    <span class="text-2xl font-black text-primary-600 dark:text-primary-400 leading-none">{{ $item->count }}</span>
                    <span class="text-[8px] font-bold text-gray-400 uppercase">Tugas</span>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="h-1.5 w-full bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden p-0.5">
                <div 
                    class="h-full bg-primary-500 rounded-full transition-all duration-1000 ease-out"
                    style="width: {{ ($item->count / $maxCount) * 100 }}%"
                ></div>
            </div>
        </div>
    </div>
</div>
