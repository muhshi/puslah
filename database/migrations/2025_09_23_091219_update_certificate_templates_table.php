<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            // buang kolom yang tidak dipakai
            $table->dropColumn(['blade_view', 'html', 'qr_config']);

            // rename/ubah existing
            $table->string('background_path')->nullable()->change();

            // tambahkan kolom baru
            $table->string('code')->unique()->after('name');
            $table->string('paper')->default('a4')->after('background_path');
            $table->string('orientation')->default('landscape')->after('paper');

            $table->unsignedSmallInteger('margin_top')->default(40)->after('orientation');
            $table->unsignedSmallInteger('margin_right')->default(40);
            $table->unsignedSmallInteger('margin_bottom')->default(40);
            $table->unsignedSmallInteger('margin_left')->default(40);

            $table->unsignedSmallInteger('qr_left')->default(60)->after('margin_left');
            $table->unsignedSmallInteger('qr_top')->default(60);
            $table->unsignedSmallInteger('qr_size')->default(220);

            $table->string('city_label')->nullable()->after('signer_image_path');
            $table->boolean('active')->default(true)->after('city_label');
        });

        Schema::table('certificates', function (Blueprint $table) {
            if (!Schema::hasColumn('certificates', 'certificate_template_id')) {
                $table->foreignId('certificate_template_id')
                    ->nullable()
                    ->constrained('certificate_templates')
                    ->nullOnDelete()
                    ->after('user_id');
            }
            if (!Schema::hasColumn('certificates', 'signature_date')) {
                $table->date('signature_date')->nullable()->after('issued_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'paper',
                'orientation',
                'margin_top',
                'margin_right',
                'margin_bottom',
                'margin_left',
                'qr_left',
                'qr_top',
                'qr_size',
                'city_label',
                'active',
            ]);

            // kembalikan kolom lama
            $table->text('blade_view')->nullable();
            $table->text('html')->nullable();
            $table->json('qr_config')->nullable();
        });

        Schema::table('certificates', function (Blueprint $table) {
            if (Schema::hasColumn('certificates', 'certificate_template_id')) {
                $table->dropConstrainedForeignId('certificate_template_id');
            }
            if (Schema::hasColumn('certificates', 'signature_date')) {
                $table->dropColumn('signature_date');
            }
        });
    }
};
