<?php

namespace Tests\Feature;

// Ensure local worktree classes in app/ are loaded before main repo autoload
spl_autoload_register(function (string $class) {
    if (str_starts_with($class, 'App\\')) {
        $file = __DIR__.'/../../app/'.str_replace('\\', '/', substr($class, 4)).'.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
}, true, true);

use App\Exports\PelatihanSpjExport;
use App\Models\SuratTugas;
use App\Models\Survey;
use App\Models\SurveyUser;
use App\Models\User;
use App\Services\PelatihanSpjDataBuilder;
use App\Services\PelatihanUndanganService;
use App\Services\SipetraUserDataService;
use App\Settings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpWord\PhpWord;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

class PelatihanDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSettings::fake(SystemSettings::defaults());
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Organik', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Mitra', 'guard_name' => 'web']);
        Gate::before(fn () => true);
    }

    /**
     * Create standard test fixture for Pelatihan with 3 participants across 2 classes (including 1 Inda).
     */
    protected function createPelatihanFixture(bool $includeAtk = false): array
    {
        // 1. Officials
        app(SystemSettings::class)->fill([
            'cert_signer_name' => 'Khomarudin, S.ST',
            'cert_signer_nip' => '197512091999011001',
        ])->save();

        $ppk = User::factory()->create(['name' => 'Sulaeman PPK']);
        $ppk->profile->update([
            'full_name' => 'Sulaeman, S.Stat',
            'nip' => '198204012006041015',
            'jabatan' => 'Statistisi Ahli Muda',
        ]);

        $bendahara = User::factory()->create(['name' => 'Khusnul Bendahara']);
        $bendahara->profile->update([
            'full_name' => 'Khusnul Khotimah, S.Stat',
            'nip' => '198610252009022009',
            'jabatan' => 'Pranata Keuangan APBN',
        ]);

        $penyelenggara = User::factory()->create(['name' => 'Ahmad Panitia']);
        $penyelenggara->profile->update([
            'full_name' => 'Ahmad Fauzi, S.Tr.Stat',
            'nip' => '199001012012011002',
            'jabatan' => 'Ketua Panitia Pelatihan',
        ]);

        // 2. Survey Pelatihan (2 days: 2026-11-24 to 2026-11-25)
        $survey = Survey::create([
            'name' => 'Pelatihan Petugas Sensus Ekonomi 2026',
            'description' => 'Pelatihan tatap muka petugas pencacah lapangan',
            'is_pelatihan' => true,
            'start_date' => '2026-11-24',
            'end_date' => '2026-11-25',
            'lokasi' => 'Hotel Demak Grand',
            'kota_kegiatan' => 'Demak',
            'nomor_surat_undangan' => 'B-0123/33210/KU.010/2026',
            'tanggal_surat_undangan' => '2026-11-20',
            'rujukan_surat' => 'DIPA BPS Kabupaten Demak Tahun Anggaran 2026',
            'link_materi' => 's.bps.go.id/materi-se2026',
            'program' => '054.01.GG Program Penyediaan Statistik',
            'kegiatan_dipa' => '2902 Penyediaan Data dan Statistik',
            'klasifikasi_ro' => '006 Sensus',
            'rincian_output' => '005 Pelaksanaan Sensus',
            'komponen' => 'A Pengumpulan Data',
            'mak' => '524113 / 521219',
            'biaya_transport' => 150000,
            'biaya_uang_harian' => 100000,
            'penyelenggara_user_id' => $penyelenggara->id,
            'ppk_user_id' => $ppk->id,
            'bendahara_user_id' => $bendahara->id,
            'include_atk' => $includeAtk,
            'atk_items' => ['Totebag', 'Pulpen BPS', 'Pensil 2B', 'Blocknote'],
        ]);

        // 3. Surat Tugas
        $suratTugas = SuratTugas::create([
            'survey_id' => $survey->id,
            'user_id' => $penyelenggara->id,
            'nomor_surat' => 'B-0456/33210/KP.650/2026',
            'nomor_urut' => 456,
            'kode_klasifikasi' => 'KP.650',
            'jabatan' => 'Ketua Panitia Pelatihan',
            'tanggal' => '2026-11-22',
            'waktu_mulai' => '2026-11-24',
            'waktu_selesai' => '2026-11-25',
            'keperluan' => 'Pelatihan Petugas Sensus Ekonomi 2026',
            'tempat_tugas' => 'Hotel Demak Grand',
            'signer_name' => 'Khomarudin, S.ST',
            'signer_nip' => '197512091999011001',
            'signer_title' => 'Kepala Badan Pusat Statistik Kabupaten Demak',
            'signer_city' => 'Demak',
            'status' => 'approved',
        ]);

        // 4. Participants
        // Participant 1: PPL in Kelas A
        $user1 = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);
        $user1->profile->update(['full_name' => 'Budi Santoso, S.Si', 'nik' => '3321010101900001', 'kecamatan' => 'Demak']);

        // Participant 2: PML in Kelas A
        $user2 = User::factory()->create(['name' => 'Siti Aminah', 'email' => 'siti@example.com']);
        $user2->profile->update(['full_name' => 'Siti Aminah, S.Stat', 'nip' => '199205052015022001', 'kecamatan' => 'Sayung']);

        // Participant 3: Inda in Kelas B
        $user3 = User::factory()->create(['name' => 'Joko Widodo Inda', 'email' => 'joko@example.com']);
        $user3->profile->update(['full_name' => 'Joko Widodo, M.Si', 'nip' => '198001012005011001', 'kecamatan' => 'Wonosalam']);

        $su1 = SurveyUser::create([
            'survey_id' => $survey->id,
            'user_id' => $user1->id,
            'role_pelatihan' => 'PPL',
            'kelas' => 'Kelas A',
            'asal' => 'Demak',
            'tujuan' => 'Hotel Demak Grand',
            'transport_override' => null,
            'uang_harian_override' => null,
        ]);

        $su2 = SurveyUser::create([
            'survey_id' => $survey->id,
            'user_id' => $user2->id,
            'role_pelatihan' => 'PML',
            'kelas' => 'Kelas A',
            'asal' => 'Sayung',
            'tujuan' => 'Hotel Demak Grand',
            'transport_override' => 170000,
            'uang_harian_override' => null,
        ]);

        $su3 = SurveyUser::create([
            'survey_id' => $survey->id,
            'user_id' => $user3->id,
            'role_pelatihan' => 'Inda',
            'kelas' => 'Kelas B',
            'asal' => 'Wonosalam',
            'tujuan' => 'Hotel Demak Grand',
            'transport_override' => null,
            'uang_harian_override' => 120000,
        ]);

        return [
            'survey' => $survey,
            'ppk' => $ppk,
            'bendahara' => $bendahara,
            'penyelenggara' => $penyelenggara,
            'suratTugas' => $suratTugas,
            'users' => [$user1, $user2, $user3],
        ];
    }

    public function test_pelatihan_spj_data_builder_calculations(): void
    {
        $fixture = $this->createPelatihanFixture(includeAtk: false);
        /** @var Survey $survey */
        $survey = $fixture['survey'];

        $u1 = $fixture['users'][0];
        $u2 = $fixture['users'][1];

        // Mock SipetraUserDataService to provide mock bank accounts
        $this->mock(SipetraUserDataService::class, function ($mock) use ($u1, $u2) {
            $mock->shouldReceive('fetchForUsers')->andReturn([
                $u1->id => (object) [
                    'id' => $u1->id,
                    'nik' => '3321010101900001',
                    'nama_bank' => 'BRI',
                    'nomor_rekening' => '588501046131533',
                    'nama_pemilik_rekening' => 'Budi Santoso',
                ],
                $u2->id => (object) [
                    'id' => $u2->id,
                    'nik' => '3321020202920002',
                    'nama_bank' => 'Mandiri',
                    'nomor_rekening' => '1350012345678',
                    'nama_pemilik_rekening' => 'Siti Aminah',
                ],
            ]);
            $mock->shouldReceive('extractAsal')->andReturn('Demak');
        });

        $builder = app(PelatihanSpjDataBuilder::class);
        $data = $builder->build($survey);

        // 1. Duration & dates
        $this->assertEquals(2, $data['jumlah_hari'], 'Duration should be 2 inclusive days');
        $this->assertCount(2, $data['dates']);
        $this->assertStringContainsString('24', $data['periode']);
        $this->assertStringContainsString('25', $data['periode']);

        // 2. Surat Tugas resolution
        $this->assertEquals('B-0456/33210/KP.650/2026', $data['nomor_surat_tugas']);

        // 3. Officials resolution (stripped academic titles)
        $this->assertEquals('Sulaeman', $data['ppk']['name']);
        $this->assertEquals('Khusnul Khotimah', $data['bendahara']['name']);
        $this->assertEquals('Ahmad Fauzi', $data['penyelenggara']['name']);

        // 4. Classes grouping
        // all_classes should contain Kelas A and Kelas B
        $this->assertCount(2, $data['all_classes']);
        $this->assertEquals('Kelas A', $data['all_classes'][0]['kelas_nama']);
        $this->assertCount(2, $data['all_classes'][0]['rows']);
        $this->assertEquals('Kelas B', $data['all_classes'][1]['kelas_nama']);
        $this->assertCount(1, $data['all_classes'][1]['rows']);

        // attendance_classes should exclude Inda (Kelas A only)
        $this->assertCount(1, $data['attendance_classes']);
        $this->assertEquals('Kelas A', $data['attendance_classes'][0]['kelas_nama']);
        $this->assertCount(2, $data['attendance_classes'][0]['rows']);

        // Indas should contain 1 participant
        $this->assertCount(1, $data['indas']);
        $this->assertEquals('Joko Widodo, M.Si', $data['indas'][0]['nama']);

        // 5. Payment rows calculations
        // Participant 1 (Budi): transport 150.000 + (100.000 * 2) = 350.000
        // Participant 2 (Siti): transport 170.000 (override) + (100.000 * 2) = 370.000
        // Participant 3 (Joko): transport 150.000 + (120.000 override * 2) = 390.000
        // Grand Total: 350.000 + 370.000 + 390.000 = 1.110.000
        $this->assertCount(3, $data['payment_rows']);
        $this->assertEquals(350000, $data['payment_rows'][0]['total']);
        $this->assertEquals(370000, $data['payment_rows'][1]['total']);
        $this->assertEquals(390000, $data['payment_rows'][2]['total']);

        $this->assertEquals(1110000, $data['grand_total']);
        $this->assertStringContainsString('satu juta seratus sepuluh ribu rupiah', strtolower($data['terbilang']));
    }

    public function test_download_undangan_word_generates_valid_docx_with_multiple_classes(): void
    {
        $fixture = $this->createPelatihanFixture(includeAtk: false);
        /** @var Survey $survey */
        $survey = $fixture['survey'];

        $service = app(PelatihanUndanganService::class);
        $response = $service->downloadUndanganWord($survey);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $filePath = $response->getFile()->getPathname();
        $this->assertFileExists($filePath);

        // Inspect docx zip structure and document.xml contents
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($filePath), 'Failed to open generated .docx as ZIP');
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertNotEmpty($xml);
        $this->assertStringContainsString('Undangan Pelatihan Petugas Sensus Ekonomi 2026', $xml);
        $this->assertStringNotContainsString('Undangan Pelatihan Pelatihan', $xml);
        $this->assertStringContainsString('Khomarudin', $xml);
        $this->assertStringNotContainsString('Khomarudin, S.ST', $xml);
        $this->assertStringContainsString('Budi Santoso', $xml);
        $this->assertStringContainsString('Siti Aminah', $xml);
        $this->assertStringContainsString('Joko Widodo', $xml);
        $this->assertStringContainsString('Kelas A', $xml);
        $this->assertStringContainsString('Kelas B', $xml);
        $this->assertStringNotContainsString('tautan:', $xml);
        $this->assertStringNotContainsString('s.bps.go.id/materi-pelatihan', $xml);
    }

    public function test_render_daftar_hadir_pdf_generates_valid_pdf_stream(): void
    {
        $fixture = $this->createPelatihanFixture(includeAtk: false);
        /** @var Survey $survey */
        $survey = $fixture['survey'];

        $service = app(PelatihanUndanganService::class);
        $pdf = $service->renderDaftarHadirPdf($survey);

        $output = $pdf->output();
        $this->assertStringStartsWith('%PDF-', $output);
        $this->assertGreaterThan(500, strlen($output));
    }

    public function test_render_pernyataan_riil_and_tidak_kendaraan_pdfs_generate_valid_streams(): void
    {
        $fixture = $this->createPelatihanFixture(includeAtk: false);
        /** @var Survey $survey */
        $survey = $fixture['survey'];

        $service = app(PelatihanUndanganService::class);

        $riilPdf = $service->renderPernyataanRiilPdf($survey)->output();
        $this->assertStringStartsWith('%PDF-', $riilPdf);
        $this->assertGreaterThan(500, strlen($riilPdf));

        $tidakKendaraanPdf = $service->renderPernyataanTidakKendaraanPdf($survey)->output();
        $this->assertStringStartsWith('%PDF-', $tidakKendaraanPdf);
        $this->assertGreaterThan(500, strlen($tidakKendaraanPdf));

        // Verify HTML renders individual pages per participant without "Terlampir"
        $data = app(PelatihanSpjDataBuilder::class)->build($survey);
        $riilHtml = view('pdf.pernyataan_riil_pelatihan', ['survey' => $survey, 'data' => $data])->render();
        $this->assertStringContainsString('PERNYATAAN PENGELUARAN RIIL', $riilHtml);
        $this->assertStringContainsString('Sulaeman', $riilHtml);
        $this->assertStringContainsString('Khomarudin', $riilHtml);
        $this->assertStringContainsString('Budi Santoso', $riilHtml);
        $this->assertStringContainsString('Siti Aminah', $riilHtml);
        $this->assertStringContainsString('Joko Widodo', $riilHtml);
        $this->assertSame(3, substr_count($riilHtml, 'PERNYATAAN PENGELUARAN RIIL'));
        $this->assertStringNotContainsString('Terlampir', $riilHtml);
        $this->assertStringNotContainsString('Sulaeman, S.Stat', $riilHtml);
        $this->assertStringNotContainsString('Khomarudin, S.ST', $riilHtml);

        $tdkHtml = view('pdf.pernyataan_tidak_kendaraan_pelatihan', ['survey' => $survey, 'data' => $data])->render();
        $this->assertStringContainsString('SURAT PERNYATAAN', $tdkHtml);
        $this->assertStringContainsString('tidak menggunakan kendaraan dinas', $tdkHtml);
        $this->assertStringContainsString('Budi Santoso', $tdkHtml);
        $this->assertStringContainsString('Siti Aminah', $tdkHtml);
        $this->assertStringContainsString('Joko Widodo', $tdkHtml);
        $this->assertSame(3, substr_count($tdkHtml, 'SURAT PERNYATAAN'));
        $this->assertStringNotContainsString('Terlampir', $tdkHtml);
        $this->assertStringContainsString('B-0456/33210/KP.650/2026', $tdkHtml);
    }

    public function test_render_bundel_spj_per_kelas_pdf_and_zip(): void
    {
        $fixture = $this->createPelatihanFixture(includeAtk: true);
        /** @var Survey $survey */
        $survey = $fixture['survey'];

        $service = app(PelatihanUndanganService::class);
        $this->assertSame(['Kelas A', 'Kelas B'], $service->getAvailableClasses($survey));

        // 1 PDF for Kelas A only
        $kelasAPdf = $service->renderBundelSpjKelasPdf($survey, 'Kelas A')->output();
        $this->assertStringStartsWith('%PDF-', $kelasAPdf);
        $this->assertGreaterThan(1000, strlen($kelasAPdf));

        // Verify HTML for Kelas A contains Kelas A participants & NOT Kelas B Inda Joko
        $data = app(PelatihanSpjDataBuilder::class)->build($survey);
        $kelasAPaymentRows = array_values(array_filter($data['payment_rows'], fn ($r) => $r['kelas'] === 'Kelas A'));
        $html = view('pdf.bundel_spj_pelatihan', [
            'survey' => $survey,
            'data' => $data,
            'kelasNama' => 'Kelas A',
            'classAttendanceRows' => $data['attendance_classes'][0]['rows'],
            'classPaymentRows' => $kelasAPaymentRows,
            'classGrandTotal' => array_sum(array_column($kelasAPaymentRows, 'total')),
            'classTerbilang' => 'tujuh ratus dua puluh ribu rupiah',
            'logoBase64' => null,
        ])->render();

        $this->assertStringContainsString('Daftar Hadir Peserta Pelatihan', $html);
        $this->assertStringContainsString('Daftar Hadir Instruktur Daerah', $html);
        $this->assertStringContainsString('Daftar Bukti Penerimaan Uang Transport', $html);
        $this->assertStringContainsString('Lampiran Laporan Pelaksanaan Dinas', $html);
        $this->assertStringContainsString('Tanda Terima Perlengkapan Peserta', $html);
        $this->assertStringContainsString('PERNYATAAN PENGELUARAN RIIL', $html);
        $this->assertStringContainsString('SURAT PERNYATAAN', $html);
        $this->assertStringContainsString('Budi Santoso', $html);
        $this->assertStringContainsString('Siti Aminah', $html);
        $this->assertSame(2, substr_count($html, 'PERNYATAAN PENGELUARAN RIIL'));
        $this->assertSame(2, substr_count($html, 'SURAT PERNYATAAN'));
        $this->assertStringNotContainsString('Terlampir', $html);

        // ZIP containing 1 PDF per class when all classes requested
        $zipResponse = $service->downloadBundelSpjKelas($survey, '__ALL_ZIP__');
        $zipPath = $zipResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath));
        $this->assertSame(2, $zip->numFiles);
        $zip->close();
    }

    public function test_pelatihan_spj_export_generates_valid_xlsx_with_4_sheets_when_atk_false(): void
    {
        $fixture = $this->createPelatihanFixture(includeAtk: false);
        /** @var Survey $survey */
        $survey = $fixture['survey'];

        $export = new PelatihanSpjExport($survey);
        $fileName = 'test_spj_no_atk.xlsx';
        Storage::disk('local')->delete($fileName);

        Excel::store($export, $fileName, 'local');
        $this->assertTrue(Storage::disk('local')->exists($fileName));

        $fullPath = Storage::disk('local')->path($fileName);
        $spreadsheet = IOFactory::load($fullPath);

        // Assert exactly 4 rekap sheets when ATK is false
        $this->assertEquals(4, $spreadsheet->getSheetCount(), 'Should have 4 sheets when include_atk is false');

        $expectedSheetTitles = [
            'Daftar Hadir',
            'Daftar Hadir INDA',
            'Bukti Penerimaan Uang',
            'Lampiran LPD',
        ];
        $this->assertEquals($expectedSheetTitles, $spreadsheet->getSheetNames());

        // Check Bukti Penerimaan Uang sheet formulas and print PageSetup
        $sheet = $spreadsheet->getSheetByName('Bukti Penerimaan Uang');
        $this->assertNotNull($sheet);
        $this->assertTrue($sheet->getPageSetup()->getFitToPage());
        $this->assertSame(1, $sheet->getPageSetup()->getFitToWidth());
        $this->assertSame(PageSetup::ORIENTATION_LANDSCAPE, $sheet->getPageSetup()->getOrientation());

        // Check multiplication formula in row 12 (data rows start at 12)
        $k12Formula = $sheet->getCell('K12')->getValue();
        $this->assertStringContainsString('H12', (string) $k12Formula);
        $this->assertStringContainsString('I12', (string) $k12Formula);
        $this->assertStringContainsString('J12', (string) $k12Formula);

        // Check active SUM formula in total row
        // Data has 3 participants, so rows are 12, 13, 14, and Total is row 15
        $k15Formula = $sheet->getCell('K15')->getValue();
        $this->assertStringStartsWith('=SUM(', (string) $k15Formula);

        Storage::disk('local')->delete($fileName);
    }

    public function test_pelatihan_spj_export_generates_5_sheets_when_atk_true(): void
    {
        $fixture = $this->createPelatihanFixture(includeAtk: true);
        /** @var Survey $survey */
        $survey = $fixture['survey'];

        $export = new PelatihanSpjExport($survey);
        $fileName = 'test_spj_with_atk.xlsx';
        Storage::disk('local')->delete($fileName);

        Excel::store($export, $fileName, 'local');
        $this->assertTrue(Storage::disk('local')->exists($fileName));

        $fullPath = Storage::disk('local')->path($fileName);
        $spreadsheet = IOFactory::load($fullPath);

        // Assert exactly 5 sheets when ATK is true
        $this->assertEquals(5, $spreadsheet->getSheetCount(), 'Should have 5 sheets when include_atk is true');

        $expectedSheetTitles = [
            'Daftar Hadir',
            'Daftar Hadir INDA',
            'Bukti Penerimaan Uang',
            'Lampiran LPD',
            'Tanda Terima ATK',
        ];
        $this->assertEquals($expectedSheetTitles, $spreadsheet->getSheetNames());

        Storage::disk('local')->delete($fileName);
    }

    public function test_pelatihan_spj_export_includes_sipetra_bank_data(): void
    {
        $fixture = $this->createPelatihanFixture(includeAtk: false);
        /** @var Survey $survey */
        $survey = $fixture['survey'];
        $u1 = $fixture['users'][0];

        $this->mock(SipetraUserDataService::class, function ($mock) use ($u1) {
            $mock->shouldReceive('fetchForUsers')->andReturn([
                $u1->id => (object) [
                    'id' => $u1->id,
                    'nik' => '3321010101900001',
                    'nama_bank' => 'Bank Jateng Syariah',
                    'nomor_rekening' => '5023019999',
                    'nama_pemilik_rekening' => 'Budi Santoso',
                ],
            ]);
            $mock->shouldReceive('extractAsal')->andReturn('Demak');
        });

        $export = new PelatihanSpjExport($survey);
        $fileName = 'test_spj_sipetra_bank.xlsx';
        Storage::disk('local')->delete($fileName);

        Excel::store($export, $fileName, 'local');
        $this->assertTrue(Storage::disk('local')->exists($fileName));

        $fullPath = Storage::disk('local')->path($fileName);
        $spreadsheet = IOFactory::load($fullPath);
        $sheet = $spreadsheet->getSheetByName('Bukti Penerimaan Uang');

        $this->assertNotNull($sheet);
        // Participant 1 in row 12
        $this->assertEquals('Bank Jateng Syariah', $sheet->getCell('D12')->getValue());
        $this->assertEquals('5023019999', $sheet->getCell('E12')->getValue());

        Storage::disk('local')->delete($fileName);
    }

    public function test_pelatihan_spj_data_builder_resolves_officials_from_system_settings(): void
    {
        $settings = app(SystemSettings::class);
        $settings->fill([
            'cert_signer_name' => 'Khomarudin, S.ST',
            'cert_signer_nip' => '197512091999011001',
            'ppk_name' => 'Sulaeman, S.Stat',
            'ppk_nip' => '197001011995011001',
            'ppk_title' => 'Pejabat Pembuat Komitmen',
            'bendahara_name' => 'Khusnul Khotimah, A.Md, S.Stat.',
            'bendahara_nip' => '198002022005012002',
            'bendahara_title' => 'Bendahara Pengeluaran',
            'kasubag_umum_name' => 'Ahmad Fauzi, S.Tr.Stat',
            'kasubag_umum_nip' => '197903032004011003',
            'kasubag_umum_title' => 'Kepala Subbagian Umum',
        ])->save();

        $survey = Survey::create([
            'name' => 'Pelatihan Menggunakan Pengaturan Sistem',
            'is_pelatihan' => true,
            'start_date' => '2026-11-24',
            'end_date' => '2026-11-25',
            'lokasi' => 'Aula BPS Kabupaten Demak',
            'kota_kegiatan' => 'Demak',
        ]);

        $this->mock(SipetraUserDataService::class, function ($mock) {
            $mock->shouldReceive('fetchForUsers')->andReturn([]);
        });

        $data = app(PelatihanSpjDataBuilder::class)->build($survey);

        $this->assertSame('Khomarudin', $data['kepala']['name']);
        $this->assertSame('Sulaeman', $data['ppk']['name']);
        $this->assertSame('197001011995011001', $data['ppk']['nip']);
        $this->assertSame('Pejabat Pembuat Komitmen', $data['ppk']['title']);

        $this->assertSame('Khusnul Khotimah', $data['bendahara']['name']);
        $this->assertSame('198002022005012002', $data['bendahara']['nip']);
        $this->assertSame('Bendahara Pengeluaran', $data['bendahara']['title']);

        $this->assertSame('Ahmad Fauzi', $data['penyelenggara']['name']);
        $this->assertSame('197903032004011003', $data['penyelenggara']['nip']);
        $this->assertSame('Kepala Subbagian Umum', $data['penyelenggara']['title']);
    }

    public function test_download_undangan_word_handles_custom_template_without_kelas_block_or_row_vars(): void
    {
        $fixture = $this->createPelatihanFixture(includeAtk: false);
        /** @var Survey $survey */
        $survey = $fixture['survey'];

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText('Nomor: ${nomor_surat}');
        $section->addText('Hal: ${hal}');
        $section->addText('${kelas_block}Tabel Statis${/kelas_block}');

        Storage::disk('public')->makeDirectory('templates');
        $customRelPath = 'templates/test_custom_no_rows.docx';
        $phpWord->save(Storage::disk('public')->path($customRelPath), 'Word2007');

        $settings = app(SystemSettings::class);
        $settings->fill(['undangan_pelatihan_template_path' => $customRelPath])->save();

        $response = app(PelatihanUndanganService::class)->downloadUndanganWord($survey);
        $filePath = $response->getFile()->getPathname();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($filePath));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertStringContainsString('B-0123/33210/KU.010/2026', $xml);
        $this->assertStringContainsString('Undangan Pelatihan Petugas Sensus Ekonomi 2026', $xml);

        Storage::disk('public')->delete($customRelPath);
    }
}
