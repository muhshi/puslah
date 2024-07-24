<div>
    <div class="container mx-auto">
        <div class="bg-white p-6 rounded-lg mt-3 shadow-lg">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <h2 class="text-2xl font-semibold text-black dark:text-white">Informasi Pegawai</h2>
                    <div class="bg-gray-100 p-4 rounded-lg shadow-lg mt-4">
                        <p><strong>Nama Pegawai:</strong> {{ Auth::user()->name }}</p>
                        <p><strong>Kantor : </strong>{{ $schedule->office->name }}</p>
                        <p><strong>Shift :</strong> {{ $schedule->shift->name }} ({{ $schedule->shift->start_time }} -
                            {{ $schedule->shift->end_time }}) WIB</p>
                    </div>
                </div>

                <div>
                    <h2 class="text-2xl font-semibold text-black dark:text-white"> Presensi </h2>
                    <div id="map" class="mb-4 rounded-lg border border-gray-300"></div>
                    <button type="button" onclick="tagLocation()" class="px-4 py-2 bg-blue-500 text-white rounded">Tag
                        Location</button>
                    <button type="button" class="px-4 py-2 bg-green-500 text-white rounded">Submit</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        const map = L.map('map').setView([{{ $schedule->office->latitude }}, {{ $schedule->office->longitude }}], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        const office = L.latLng({{ $schedule->office->latitude }}, {{ $schedule->office->longitude }});
        L.marker(office).addTo(map);

        const radius = {{ $schedule->office->radius }};
        L.circle(office, radius).addTo(map);

        function tagLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition((position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;

                    if (marker) {
                        map.removeLayer(marker);
                    }
                    marker = L.marker([lat, lng]).addTo(map);
                    map.setView([lat, lng], 16);

                    if (isWithinRadius(lat, lng, office, radius)) {
                        alert("Anda berada di lokasi kerja.");
                    } else {
                        alert("Anda diluar lokasi kerja.");
                    }
                })
            } else {
                alert("Tidak bisa tag location.");
            }
        }

        function isWithinRadius(lat, lng, center, radius) {
            let distance = map.distance([lat, lng], center);
            return distance <= radius;
        }
    </script>
</div>
