@php
    $riilRows = $paymentRows ?? [];
    if (empty($riilRows)) {
        $riilRows = [[
            'nama_tanpa_gelar' => $data['penyelenggara']['name'] ?? '-',
            'nama' => $data['penyelenggara']['name'] ?? '-',
            'nip' => $data['penyelenggara']['nip'] ?? '-',
            'nik' => '-',
            'nik_or_nip' => $data['penyelenggara']['nip'] ?? '-',
            'role_pelatihan' => $data['penyelenggara']['title'] ?? 'Peserta',
            'asal' => 'BPS Kabupaten Demak',
            'tujuan' => $data['lokasi'] ?: '-',
            'transport' => 0,
            'surat_tugas_nomor' => $data['nomor_surat_tugas'] ?: '-',
            'surat_tugas_tanggal' => $data['tanggal_surat_tugas'],
        ]];
    }
@endphp

@foreach ($riilRows as $row)
    @php
        $hasNip = filled($row['nip'] ?? null) && trim((string) $row['nip']) !== '-';
        $hasNik = filled($row['nik'] ?? null) && trim((string) $row['nik']) !== '-';
        $idLabel = $hasNip ? 'NIP' : 'NIK';
        $idValue = $hasNip ? $row['nip'] : ($hasNik ? $row['nik'] : '-');
        $displayName = $row['nama_tanpa_gelar'] ?? ($row['nama'] ?? '-');
        $transport = (int) ($row['transport'] ?? 0);
        $stNomor = $row['surat_tugas_nomor'] ?: ($data['nomor_surat_tugas'] ?: '-');
        $stTanggal = $row['surat_tugas_tanggal'] ?? $data['tanggal_surat_tugas'];
    @endphp
    <div class="statement-page riil-page {{ ! empty($isBundled) ? 'bundled-statement' : '' }}">
        <div class="stmt-title">PERNYATAAN PENGELUARAN RIIL</div>

        <div>Yang bertanda tangan di bawah ini :</div>

        <table class="stmt-identity-table">
            <tr>
                <td class="stmt-identity-label">Nama</td>
                <td class="stmt-identity-colon">:</td>
                <td>{{ $displayName }}</td>
            </tr>
            <tr>
                <td class="stmt-identity-label">{{ $idLabel }}</td>
                <td class="stmt-identity-colon">:</td>
                <td>{{ $idValue }}</td>
            </tr>
            <tr>
                <td class="stmt-identity-label">Jabatan</td>
                <td class="stmt-identity-colon">:</td>
                <td>{{ $row['role_pelatihan'] ?? 'Peserta' }} {{ $survey->name ?? 'Pelatihan' }}</td>
            </tr>
        </table>

        <div class="stmt-paragraph">
            Berdasarkan Surat Tugas Nomor: {{ $stNomor }} Tanggal {{ $stTanggal }}
            dengan ini kami menyatakan dengan sesungguhnya bahwa biaya transport Pulang Pergi (PP) selama {{ $data['jumlah_hari'] }} hari
            Petugas di bawah ini yang tidak dapat diperoleh bukti-bukti pengeluaran sebagai berikut:
        </div>

        <table class="stmt-summary-table">
            <thead>
                <tr>
                    <th style="width: 7%;">No</th>
                    <th style="width: 31%;">Asal/dari</th>
                    <th style="width: 37%;">Tujuan</th>
                    <th style="width: 25%;">Biaya (Rp)</th>
                </tr>
                <tr class="stmt-sub-num-row">
                    <td>1</td>
                    <td>2</td>
                    <td>3</td>
                    <td>4</td>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center">1</td>
                    <td>{{ $row['asal'] ?: 'Demak' }}</td>
                    <td class="text-center">{{ $data['lokasi'] ?: ($row['tujuan'] ?: '-') }}</td>
                    <td class="text-right">Rp {{ number_format($transport, 0, ',', '.') }},-</td>
                </tr>
                <tr>
                    <td colspan="3" class="text-center"><strong>Jumlah</strong></td>
                    <td class="text-right"><strong>Rp {{ number_format($transport, 0, ',', '.') }},-</strong></td>
                </tr>
            </tbody>
        </table>

        <div class="stmt-paragraph">
            Jumlah uang tersebut di atas benar-benar dikeluarkan untuk pelaksanaan dimaksud dan apabila di kemudian hari terdapat
            kelebihan atas pembayaran, kami bersedia untuk menyetorkan kelebihan tersebut ke Kas Negara.
        </div>

        <div class="stmt-paragraph">
            Demikian pernyataan ini kami buat dengan sebenarnya, untuk dipergunakan sebagaimana mestinya.
        </div>

        <table class="stmt-sig-table">
            <tr>
                <td>
                    Menyetujui,<br>
                    Pejabat Pembuat Komitmen,
                    <div class="stmt-sig-space"></div>
                    <span class="stmt-sig-name">{{ $data['ppk']['name'] ?? '-' }}</span><br>
                    NIP. {{ $data['ppk']['nip'] ?? '-' }}
                </td>
                <td>
                    Demak, {{ $tanggalAkhir }}<br>
                    Pelaksana perjalanan,
                    <div class="stmt-sig-space"></div>
                    <span class="stmt-sig-name">{{ $displayName }}</span><br>
                    @if ($idValue !== '-')
                        {{ $idLabel }}. {{ $idValue }}
                    @endif
                </td>
            </tr>
        </table>

        <div class="stmt-sig-center-block">
            Mengetahui,<br>
            BPS Kabupaten Demak<br>
            K e p a l a,
            <div class="stmt-sig-space"></div>
            <span class="stmt-sig-name">{{ $data['kepala']['name'] ?? '-' }}</span><br>
            NIP. {{ $data['kepala']['nip'] ?? '-' }}
        </div>
    </div>
@endforeach
