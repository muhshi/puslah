<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class EmployeesImport implements OnEachRow, WithHeadingRow
{
    public $success = 0;
    public $skipped = 0;
    public $failed = 0;

    /**
     * @param Row $row
     */
    public function onRow(Row $row)
    {
        $rowIndex = $row->getIndex();
        $row = $row->toArray();

        try {
            // Skip if email is missing
            if (!isset($row['email']) || !$row['email']) {
                $this->failed++;
                return;
            }

            // Check if user exists
            if (User::where('email', $row['email'])->exists()) {
                $this->skipped++;
                return;
            }

            // Create User with normalized data
            $user = User::create([
                'name' => ucwords(strtolower($row['nama'])),
                'email' => strtolower($row['email']),
                'password' => Hash::make('3321'), // Default password
            ]);

            // Update Profile with employee-specific fields
            // Profile is automatically created by User model event (booted -> created)
            $user->profile()->update([
                'jabatan' => $row['jabatan'] ?? null,
                'old_nip' => $row['nip_lama'] ?? null,
                'nip' => $row['nip_baru'] ?? null,
            ]);

            // Assign proper role (override default 'Mitra' from Observer)
            $user->syncRoles(['Pegawai BPS']);

            $this->success++;

        } catch (\Throwable $e) {
            $this->failed++;
        }
    }
}
