@php
    $tdkRows = $paymentRows ?? [];
    if (empty($tdkRows)) {
        $tdkRows = [[
            'nama_tanpa_gelar' => $data['penyelenggara']['name'] ?? '-',
            'nama' => $data['penyelenggara']['name'] ?? '-',
            'nip' => $data['penyelenggara']['nip'] ?? '-',
            'nik' => '-',
            'nik_or_nip' => $data['penyelenggara']['nip'] ?? '-',
            'pangkat_golongan' => $data['penyelenggara']['pangkat_golongan'] ?? '-',
            'jabatan' => $data['penyelenggara']['title'] ?? 'Peserta',
            'role_pelatihan' => 'Peserta',
            'surat_tugas_nomor' => $data['nomor_surat_tugas'] ?: '-',
            'surat_tugas_tanggal' => $data['tanggal_surat_tugas'],
        ]];
    }
@endphp

@foreach ($tdkRows as $row)
    @php
        $hasNip = filled($row['nip'] ?? null) && trim((string) $row['nip']) !== '-';
        $hasNik = filled($row['nik'] ?? null) && trim((string) $row['nik']) !== '-';
        $idLabel = $hasNip ? 'NIP' : 'NIK';
        $idValue = $hasNip ? $row['nip'] : ($hasNik ? $row['nik'] : '-');
        $displayName = $row['nama_tanpa_gelar'] ?? ($row['nama'] ?? '-');
        $hasPangkat = filled($row['pangkat_golongan'] ?? null) && trim((string) $row['pangkat_golongan']) !== '-';
        $stNomor = $row['surat_tugas_nomor'] ?: ($data['nomor_surat_tugas'] ?: '-');
        $stTanggal = $row['surat_tugas_tanggal'] ?? $data['tanggal_surat_tugas'];
    @endphp
    <div class="statement-page tdk-page {{ ! empty($isBundled) ? 'bundled-statement' : '' }}">
        <div class="stmt-title-underline">SURAT PERNYATAAN</div>

        <div class="stmt-intro">Yang bertanda tangan di bawah ini :</div>

        <table class="stmt-identity-table">
            <tr>
                <td class="stmt-identity-label-wide">Nama</td>
                <td class="stmt-identity-colon">:</td>
                <td>{{ $displayName }}</td>
            </tr>
            <tr>
                <td class="stmt-identity-label-wide">{{ $idLabel }}</td>
                <td class="stmt-identity-colon">:</td>
                <td>{{ $idValue }}</td>
            </tr>
            @if ($hasPangkat)
                <tr>
                    <td class="stmt-identity-label-wide">Pangkat/Gol</td>
                    <td class="stmt-identity-colon">:</td>
                    <td>{{ $row['pangkat_golongan'] }}</td>
                </tr>
            @else
                <tr>
                    <td class="stmt-identity-label-wide">Jabatan</td>
                    <td class="stmt-identity-colon">:</td>
                    <td>{{ $row['jabatan'] ?: ($row['role_pelatihan'] ?? 'Peserta') }}</td>
                </tr>
            @endif
        </table>

        <div class="stmt-paragraph-indent">
            menerangkan bahwa dalam rangka melaksanakan perjalanan dinas dalam kota untuk melaksanakan tugas kedinasan sesuai
            surat tugas nomor {{ $stNomor }} tanggal {{ $stTanggal }}
            ({{ $survey->name ?? 'Pelatihan' }}), saya benar-benar tidak menggunakan kendaraan dinas pada tanggal {{ $data['periode'] }}.
        </div>

        <div class="stmt-paragraph-indent">
            Demikian pernyataan ini kami buat dengan sebenar-benarnya untuk dipergunakan sebagaimana mestinya.
            Apabila terdapat kekeliruan dalam pertanggungjawaban SPD dan mengakibatkan kerugian negara, saya bersedia dituntut sesuai
            peraturan yang berlaku dan mengembalikan biaya transpor lokal yang sudah terlanjur saya terima ke kas negara.
        </div>

        <div class="stmt-sig-right-wrapper">
            <div class="stmt-sig-right-box">
                Demak, {{ $tanggalAkhir }}<br>
                Pelaksana Perjalanan Dinas<br>
                Dalam Kota
                <div class="stmt-sig-space-lg"></div>
                <u>{{ $displayName }}</u><br>
                @if ($idValue !== '-')
                    {{ $idLabel }}. {{ $idValue }}
                @endif
            </div>
        </div>
    </div>
@endforeach
