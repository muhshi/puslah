<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserProfile;
use App\Services\SipetraUserDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

spl_autoload_register(function (string $class): void {
    if ($class === SipetraUserDataService::class) {
        $path = __DIR__.'/../../app/Services/SipetraUserDataService.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
}, true, true);

class SipetraUserDataServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_maps_sipetra_records_by_sipetra_id_first_and_falls_back_to_lowercase_email(): void
    {
        $u1 = new User(['name' => 'Dewi', 'email' => 'dewi@example.com', 'sipetra_id' => 101]);
        $u1->id = 1;

        $u2 = new User(['name' => 'Musaadah', 'email' => 'Musa@Example.com', 'sipetra_id' => null]);
        $u2->id = 2;

        $u3 = new User(['name' => 'Budi', 'email' => 'budi@example.com', 'sipetra_id' => 999]);
        $u3->id = 3;

        $sipetraRows = collect([
            (object) [
                'id' => 101,
                'email' => 'other@example.com',
                'nik' => '3309024303909002',
                'nama_bank' => 'BRI',
                'nomor_rekening' => '588501046131533',
                'nama_pemilik_rekening' => 'Dewi Sartika',
                'alamat' => 'Demak',
            ],
            (object) [
                'id' => 202,
                'email' => 'musa@example.com',
                'nik' => '3321126706890003',
                'nama_bank' => 'BRI',
                'nomor_rekening' => '001601003908595',
                'nama_pemilik_rekening' => 'Musaadah',
                'alamat' => 'Wonosalam',
            ],
        ]);

        $service = new SipetraUserDataService;
        $mapped = $service->mapRowsToUsers(new Collection([$u1, $u2, $u3]), $sipetraRows);

        $this->assertArrayHasKey(1, $mapped);
        $this->assertEquals('BRI', $mapped[1]->nama_bank);
        $this->assertEquals('588501046131533', $mapped[1]->nomor_rekening);

        $this->assertArrayHasKey(2, $mapped);
        $this->assertEquals('3321126706890003', $mapped[2]->nik);
        $this->assertEquals('Wonosalam', $mapped[2]->alamat);

        $this->assertArrayNotHasKey(3, $mapped);
    }

    public function test_fetch_for_users_returns_empty_array_for_empty_collection_or_missing_identifiers(): void
    {
        $service = new SipetraUserDataService;

        $this->assertSame([], $service->fetchForUsers(collect()));

        $userWithoutIdOrEmail = new User(['name' => 'Anon', 'email' => null, 'sipetra_id' => null]);
        $userWithoutIdOrEmail->id = 10;
        $this->assertSame([], $service->fetchForUsers(collect([$userWithoutIdOrEmail])));
    }

    public function test_fetch_for_users_gracefully_handles_database_connection_failure(): void
    {
        config([
            'database.connections.sipetra' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 9999, // unreachable port
                'database' => 'non_existent_db',
                'username' => 'invalid_user',
                'password' => 'invalid_pass',
            ],
        ]);

        $user = new User(['name' => 'Dewi', 'email' => 'dewi@example.com', 'sipetra_id' => 101]);
        $user->id = 1;

        $service = new SipetraUserDataService;
        $result = $service->fetchForUsers(collect([$user]));

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_fetch_for_users_successfully_queries_sipetra_connection(): void
    {
        config([
            'database.connections.sipetra' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);

        Schema::connection('sipetra')->dropIfExists('users');
        Schema::connection('sipetra')->create('users', function ($table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('nik')->nullable();
            $table->string('nama_bank')->nullable();
            $table->string('nomor_rekening')->nullable();
            $table->string('nama_pemilik_rekening')->nullable();
            $table->string('alamat')->nullable();
            $table->string('nip')->nullable();
            $table->string('nip_baru')->nullable();
        });

        DB::connection('sipetra')->table('users')->insert([
            [
                'id' => 50,
                'email' => 'pegawai@bps.go.id',
                'nik' => '3321010101010001',
                'nama_bank' => 'Mandiri',
                'nomor_rekening' => '1350001234567',
                'nama_pemilik_rekening' => 'Pegawai BPS',
                'alamat' => 'Mranggen',
                'nip' => '198001012005011001',
                'nip_baru' => '198001012005011001',
            ],
            [
                'id' => 51,
                'email' => 'mitra@gmail.com',
                'nik' => '3321020202020002',
                'nama_bank' => 'BRI',
                'nomor_rekening' => '588501000999',
                'nama_pemilik_rekening' => 'Mitra BPS',
                'alamat' => 'Sayung',
                'nip' => null,
                'nip_baru' => null,
            ],
        ]);

        $u1 = new User(['name' => 'Pegawai', 'email' => 'pegawai@bps.go.id', 'sipetra_id' => 50]);
        $u1->id = 1;

        $u2 = new User(['name' => 'Mitra', 'email' => 'Mitra@GMAIL.com', 'sipetra_id' => null]);
        $u2->id = 2;

        $service = new SipetraUserDataService;
        $res = $service->fetchForUsers(collect([$u1, $u2]));

        $this->assertCount(2, $res);
        $this->assertEquals('Mandiri', $res[1]->nama_bank);
        $this->assertEquals('1350001234567', $res[1]->nomor_rekening);
        $this->assertEquals('BRI', $res[2]->nama_bank);
        $this->assertEquals('3321020202020002', $res[2]->nik);
    }

    public function test_extract_asal_resolves_known_districts_from_sipetra_row(): void
    {
        $service = new SipetraUserDataService;

        $row1 = (object) ['alamat' => 'Wonosalam'];
        $this->assertEquals('Wonosalam', $service->extractAsal($row1, null));

        $row2 = (object) ['alamat' => 'Ds. Trengguli RT 01/02, Kec. Wonosalam, Kab. Demak'];
        $this->assertEquals('Wonosalam', $service->extractAsal($row2, null));

        $row3 = (object) ['alamat' => 'Kecamatan Sayung'];
        $this->assertEquals('Sayung', $service->extractAsal($row3, null));

        $row4 = (object) ['alamat' => 'Karangtengah, Demak'];
        $this->assertEquals('Karangtengah', $service->extractAsal($row4, null));
    }

    public function test_extract_asal_falls_back_to_user_profile_address_when_sipetra_row_missing(): void
    {
        $service = new SipetraUserDataService;

        $user = new User(['name' => 'Siti']);
        $user->setRelation('profile', new UserProfile(['address' => 'Ds. Karangawen Kec. Karangawen Demak']));

        $this->assertEquals('Karangawen', $service->extractAsal(null, $user));
    }

    public function test_extract_asal_supports_kecamatan_pattern_and_custom_city(): void
    {
        $service = new SipetraUserDataService;

        $row1 = (object) ['alamat' => 'Kecamatan Gayamsari, Kota Semarang'];
        $this->assertEquals('Gayamsari', $service->extractAsal($row1, null));

        $row2 = (object) ['alamat' => 'Semarang'];
        $this->assertEquals('Semarang', $service->extractAsal($row2, null));

        $row3 = (object) ['alamat' => 'Kudus'];
        $this->assertEquals('Kudus', $service->extractAsal($row3, null));
    }

    public function test_extract_asal_falls_back_to_default_or_custom_fallback(): void
    {
        $service = new SipetraUserDataService;

        $this->assertEquals('Demak', $service->extractAsal(null, null));
        $this->assertEquals('Demak', $service->extractAsal((object) ['alamat' => ''], null));
        $this->assertEquals('Demak', $service->extractAsal((object) ['alamat' => '   '], null));
        $this->assertEquals('Kota Asal', $service->extractAsal(null, null, 'Kota Asal'));
    }
}
