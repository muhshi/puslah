<?php

namespace Tests\Unit;

use App\Services\CertificateIssuanceService;
use App\Services\LemburExportService;
use App\Services\LpdExportService;
use App\Services\SppdService;
use App\Services\SuratTugasNumberingService;
use App\Services\SuratTugasPdfService;
use App\Settings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class ServiceLayerRefactoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setLocale('id');
        SystemSettings::fake(SystemSettings::defaults());
    }
    public function test_sppd_service_terbilang()
    {
        $this->assertEquals('satu', trim(SppdService::terbilang(1)));
        $this->assertEquals('sepuluh', trim(SppdService::terbilang(10)));
        $this->assertEquals('sebelas', trim(SppdService::terbilang(11)));
        $this->assertEquals('lima belas', trim(SppdService::terbilang(15)));
        $this->assertEquals('seratus', trim(SppdService::terbilang(100)));
        $this->assertEquals('seribu', trim(SppdService::terbilang(1000)));
        $this->assertEquals('seratus tujuh puluh ribu', trim(SppdService::terbilang(170000)));
        $this->assertEquals('satu juta dua ratus lima puluh ribu', trim(SppdService::terbilang(1250000)));
    }

    public function test_sppd_service_format_nomor_sppd()
    {
        $nomor = SppdService::formatNomorSppd(5, 'KP.650', 2026);
        $this->assertStringContainsString('0005', $nomor);
        $this->assertStringContainsString('SE2026', $nomor);
        $this->assertStringContainsString('KP.650', $nomor);
        $this->assertStringContainsString('2026', $nomor);
    }

    public function test_surat_tugas_numbering_service_format_nomor_surat()
    {
        $nomor = SuratTugasNumberingService::formatNomorSurat(12, 'KP.650', 2026);
        $this->assertStringContainsString('0012', $nomor);
        $this->assertStringContainsString('KP.650', $nomor);
        $this->assertStringContainsString('2026', $nomor);
    }

    public function test_surat_tugas_pdf_service_format_periode_tugas()
    {
        // Same day
        $sameDay = SuratTugasPdfService::formatPeriodeTugas('2026-03-14', '2026-03-14');
        $this->assertEquals('14 Maret 2026', $sameDay);

        // Same month & year
        $sameMonth = SuratTugasPdfService::formatPeriodeTugas('2026-03-12', '2026-03-14');
        $this->assertEquals('12 - 14 Maret 2026', $sameMonth);

        // Same year, different month
        $diffMonth = SuratTugasPdfService::formatPeriodeTugas('2026-02-28', '2026-03-02');
        $this->assertEquals('28 Februari - 02 Maret 2026', $diffMonth);

        // Different year
        $diffYear = SuratTugasPdfService::formatPeriodeTugas('2025-12-30', '2026-01-02');
        $this->assertEquals('30 Desember 2025 - 02 Januari 2026', $diffYear);

        // Null checks
        $this->assertEquals('-', SuratTugasPdfService::formatPeriodeTugas(null, '2026-01-01'));
        $this->assertEquals('-', SuratTugasPdfService::formatPeriodeTugas('2026-01-01', null));
    }

    public function test_services_can_be_resolved_from_container()
    {
        $sppdService = app(SppdService::class);
        $this->assertInstanceOf(SppdService::class, $sppdService);

        $pdfService = app(SuratTugasPdfService::class);
        $this->assertInstanceOf(SuratTugasPdfService::class, $pdfService);

        $lpdService = app(LpdExportService::class);
        $this->assertInstanceOf(LpdExportService::class, $lpdService);

        $lemburService = app(LemburExportService::class);
        $this->assertInstanceOf(LemburExportService::class, $lemburService);

        $numberingService = app(SuratTugasNumberingService::class);
        $this->assertInstanceOf(SuratTugasNumberingService::class, $numberingService);

        $certService = app(CertificateIssuanceService::class);
        $this->assertInstanceOf(CertificateIssuanceService::class, $certService);
    }
}
