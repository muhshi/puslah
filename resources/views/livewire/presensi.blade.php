<div>
    <div class="container mx-auto max-w-sm">
        <div class="bg-white p-6 rounded-lg mt-3 shadow-lg">
            <div class="grid grid-cols-1 gap-6 mb-6">
                <div>
                    <h2 class="text-2xl font-semibold text-black dark:text-white">Informasi Pegawai</h2>
                    <div class="bg-gray-100 p-4 rounded-lg shadow-lg mt-4">
                        <p><strong>Nama Pegawai:</strong> {{ Auth::user()->name }}</p>
                        <p><strong>Kantor : </strong>{{ $schedule->office->name }}</p>
                        <p><strong>Shift :</strong> {{ $schedule->shift->name }} ({{ $schedule->shift->start_time }} -
                            {{ $schedule->shift->end_time }}) WIB</p>
                        @if ($schedule->is_wfa)
                            <p class="text-green-500"><strong>Status :</strong> WFA</p>
                        @else
                            <p><strong>Status :</strong> WFO</p>
                        @endif
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-gray-100 p-4 rounded-lg shadow-lg mt-4">
                            <h4 class="text-l font-bold mb-2"> Jam Datang</h4>
                            <p><strong>{{ $attendance ? $attendance->start_time : '-' }}</strong></p>
                        </div>
                        <div class="bg-gray-100 p-4 rounded-lg shadow-lg mt-4">
                            <h4 class="text-l font-bold mb-2"> Jam Pulang</h4>
                            <p><strong>{{ $attendance ? $attendance->end_time : '-' }}</strong></p>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-100 p-4 rounded-lg shadow-lg mt-4">
                    <h2 class="text-2xl font-semibold text-black dark:text-white"> Presensi </h2>
                    <div id="map" class="mb-4 rounded-lg border border-gray-300" wire:ignore></div>
                    @if (session()->has('error'))
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative"
                            role="alert">
                            <strong class="font-bold">Error!</strong>
                            <span class="block sm:inline">{{ session('error') }}</span>
                        </div>
                    @endif
                    <form action="" class="row g-3 mt-4" wire:submit="store" enctype="multipart/form-data">
                        <button type="button" onclick="tagLocation()"
                            class="px-4 py-2 bg-blue-500 text-white rounded">Tag
                            Location</button>
                        @if ($isWithinRadius)
                            <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded">Submit</button>
                        @endif
                    </form>
                </div>

            </div>
        </div>
    </div>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        let map;
        let marker;
        let component;
        let lat, lng;
        const office = [{{ $schedule->office->latitude }}, {{ $schedule->office->longitude }}];
        const radius = {{ $schedule->office->radius }};

        document.addEventListener('livewire:initialized', function() {
            component = @this;
            map = L.map('map').setView([{{ $schedule->office->latitude }},
                {{ $schedule->office->longitude }}
            ], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            const circle = L.circle(office, {
                color: 'red',
                fillColor: '#f03',
                fillOpacity: 0.5,
                radius: radius
            }).addTo(map);
        })

        function tagLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition((position) => {
                    lat = position.coords.latitude;
                    lng = position.coords.longitude;

                    if (marker) {
                        map.removeLayer(marker);
                    }

                    marker = L.marker([lat, lng]).addTo(map);
                    map.setView([lat, lng], 16);

                    if (isWithinRadius(lat, lng, office, radius)) {
                        component.set('isWithinRadius', true);
                        component.set('latitude', lat);
                        component.set('longitude', lng);
                    }
                    // else {

                    //     @if ($schedule->is_wfa)

                    //         component.set('isWithinRadius', true);
                    //         alert('anda WFA');
                    //     @else
                    //         alert("Anda diluar lokasi kerja.");
                    //     @endif
                    // }
                })
            } else {
                alert("Tidak bisa tag location.");
            }
        }

        function isWithinRadius(lat, lng, center, radius) {
            const is_wfa = {{ $schedule->is_wfa }};
            if (is_wfa) {
                return true;
            } else {
                let distance = map.distance([lat, lng], center);
                return distance <= radius;
            }
        }
    </script>
</div>
