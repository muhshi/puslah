<div>
    <div class="container mx-auto max-w-3xl">
        <div class="bg-white p-6 rounded-lg mt-3 shadow-lg">
            <h2 class="text-2xl font-semibold text-black dark:text-white mb-4">Pengguna & Aturan Presensi</h2>

            <div class="bg-gray-100 p-4 rounded-lg shadow">
                <p><strong>Nama:</strong> {{ auth()->user()->name }}</p>

                <p class="mt-1">
                    <strong>Kantor:</strong>
                    @if ($schedule)
                        {{ $schedule->office->name }}
                    @else
                        Default ({{ $officeLat }}, {{ $officeLng }}) – radius {{ $radiusM }}m
                    @endif
                </p>

                <p class="mt-1">
                    <strong>Jam Kerja:</strong> {{ $workStart }} – {{ $workEnd }} WIB
                </p>

                @if ($schedule?->is_wfa)
                    <p class="mt-1 text-green-600"><strong>Status:</strong> WFA (geofence dibebaskan)</p>
                @else
                    <p class="mt-1"><strong>Status:</strong> WFO</p>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                <div class="bg-gray-100 p-4 rounded-lg shadow">
                    <h4 class="text-l font-bold mb-2">Jam Datang</h4>
                    <p><strong>{{ $attendance?->start_time ?? '-' }}</strong></p>
                </div>

                <div class="bg-gray-100 p-4 rounded-lg shadow">
                    <h4 class="text-l font-bold mb-2">Jam Pulang</h4>
                    <p><strong>{{ $attendance?->end_time ?? '-' }}</strong></p>
                </div>
            </div>

            <div class="bg-gray-100 p-4 rounded-lg shadow mt-6">
                <h2 class="text-2xl font-semibold text-black dark:text-white mb-3">Presensi</h2>

                <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
                <div id="map" class="mb-4 rounded-lg border border-gray-300" wire:ignore style="height: 340px;">
                </div>

                @if (session()->has('error'))
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-3">
                        <strong class="font-bold">Error!</strong>
                        <span class="block sm:inline">{{ session('error') }}</span>
                    </div>
                @endif

                <form class="row g-3" wire:submit.prevent="store" enctype="multipart/form-data">
                    <button type="button" onclick="tagLocation()" class="px-4 py-2 bg-blue-600 text-white rounded">
                        Tag Location
                    </button>

                    @php $isWfa = $schedule?->is_wfa ?? false; @endphp

                    @if ($isWithinRadius || $isWfa)
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded ml-2">
                            {{ $attendance?->start_time ? 'Check-out (replace)' : 'Check-in' }}
                        </button>
                    @endif
                </form>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        let map, marker, component;
        let lat, lng;

        const office = [{{ $officeLat }}, {{ $officeLng }}];
        const radius = {{ $radiusM }};

        document.addEventListener('livewire:initialized', function() {
            component = @this;

            map = L.map('map').setView(office, 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            L.circle(office, {
                color: 'red',
                fillColor: '#f03',
                fillOpacity: 0.45,
                radius: radius
            }).addTo(map);
        });

        function tagLocation() {
            if (!navigator.geolocation) {
                alert("Tidak bisa tag location.");
                return;
            }

            navigator.geolocation.getCurrentPosition((position) => {
                lat = position.coords.latitude;
                lng = position.coords.longitude;

                if (marker) map.removeLayer(marker);
                marker = L.marker([lat, lng]).addTo(map);
                map.setView([lat, lng], 16);

                const ok = isWithinRadius(lat, lng, office, radius);
                component.set('isWithinRadius', ok);
                if (ok) {
                    component.set('latitude', lat);
                    component.set('longitude', lng);
                }
            });
        }

        function isWithinRadius(lat, lng, center, radius) {
            const is_wfa = {{ $schedule?->is_wfa ? 'true' : 'false' }};
            if (is_wfa) return true;

            const distance = map.distance([lat, lng], center);
            return distance <= radius;
        }
    </script>
</div>
