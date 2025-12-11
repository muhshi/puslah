<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Surat Tugas</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.5;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h3,
        .header h4 {
            margin: 0;
            text-transform: uppercase;
        }

        .header hr {
            border: 1px double #000;
            margin-top: 10px;
        }

        .content {
            margin: 0 40px;
        }

        .title {
            text-align: center;
            text-transform: uppercase;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 5px;
        }

        .nomor {
            text-align: center;
            margin-bottom: 30px;
        }

        .section {
            margin-bottom: 15px;
        }

        .table-info {
            width: 100%;
        }

        .table-info td {
            vertical-align: top;
            padding: 2px;
        }

        .table-info td:first-child {
            width: 150px;
        }

        .footer {
            margin-top: 50px;
            width: 100%;
        }

        .ttd-box {
            float: right;
            width: 300px;
            text-align: left;
        }

        .signature-img {
            height: 80px;
            display: block;
            margin: 5px 0;
        }
    </style>
</head>

<body>
    <div class="header">
        <img src="{{ public_path('images/logo_bps_black.png') }}"
            style="height: 70px; float: left; position: absolute; left: 0;" alt="">
        <h4>BADAN PUSAT STATISTIK</h4>
        <h4>KABUPATEN DEMAK</h4>
        <div style="font-size: 10pt; font-weight: normal;">
            Jl. Sultan Fatah No. 10, Demak 59511<br>
            Telp: (0291) 685456, Email: bps3321@bps.go.id
        </div>
        <hr>
    </div>

    <div class="content">
        <div class="title">SURAT TUGAS</div>
        <div class="nomor">Nomor: {{ $record->nomor_surat }}</div>

        <div class="section">
            Yang bertanda tangan di bawah ini:
            <table class="table-info">
                <tr>
                    <td>Nama</td>
                    <td>: {{ $record->signer_name }}</td>
                </tr>
                <tr>
                    <td>NIP</td>
                    <td>: {{ $record->signer_nip }}</td>
                </tr>
                <tr>
                    <td>Jabatan</td>
                    <td>: {{ $record->signer_title }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            Memberi tugas kepada:
            <table class="table-info">
                <tr>
                    <td>Nama</td>
                    <td>: {{ $record->user->name }}</td>
                </tr>
                {{-- NIP User not usually in standard User model unless using profile, assuming captured or just Name
                --}}
                <tr>
                    <td>Jabatan</td>
                    <td>: {{ $record->jabatan }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div style="margin-bottom: 5px;">Untuk:</div>
            <div style="text-align: justify;">{{ $record->keperluan }}</div>
        </div>

        <div class="section">
            <table class="table-info">
                <tr>
                    <td>Waktu Pelaksanaan</td>
                    <td>: {{ $record->tanggal->translatedFormat('d F Y') }}</td>
                </tr>
                @if($record->waktu_mulai && $record->waktu_selesai)
                    <tr>
                        <td>Jam</td>
                        <td>: {{ $record->waktu_mulai->format('H:i') }} WIB - {{ $record->waktu_selesai->format('H:i') }}
                            WIB</td>
                    </tr>
                @endif
            </table>
        </div>

        <div class="section">
            Demikian surat tugas ini dibuat untuk dilaksanakan dengan penuh tanggung jawab.
        </div>

        <div class="footer">
            <div class="ttd-box">
                {{ $record->signer_city }}, {{ $record->tanggal->translatedFormat('d F Y') }}<br>
                {{ $record->signer_title }}
                <br>
                @if($record->signer_signature_path)
                    <img src="{{ storage_path('app/public/' . $record->signer_signature_path) }}" class="signature-img">
                @else
                    <br><br><br>
                @endif
                <br>
                <b><u>{{ $record->signer_name }}</u></b><br>
                NIP. {{ $record->signer_nip }}
            </div>
        </div>
    </div>
</body>

</html>