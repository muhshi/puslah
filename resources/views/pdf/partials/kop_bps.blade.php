<table class="header-table">
    <tr>
        <td style="width: 65pt; text-align: left;">
            @if(! empty($logoBase64))
                <img src="{{ $logoBase64 }}" class="header-logo" alt="Logo BPS">
            @elseif(file_exists(public_path('images/logo_bps.png')))
                <img src="{{ public_path('images/logo_bps.png') }}" class="header-logo" alt="Logo BPS">
            @endif
        </td>
        <td>
            <div class="header-title-main">BADAN PUSAT STATISTIK KABUPATEN DEMAK</div>
            <div class="header-title-sub">Jl. Sultan Hadiwijaya No. 23 Demak 59515, Telp: (0291) 685456</div>
            <div class="header-title-sub">Email: bps3321@bps.go.id  |  Website: demakkab.bps.go.id</div>
        </td>
    </tr>
</table>
