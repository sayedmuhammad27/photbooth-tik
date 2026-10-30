<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_sessions', function (Blueprint $table) {
            $table->string('google_drive_file_id')->nullable()->after('raw_photos');
            $table->text('google_drive_url')->nullable()->after('google_drive_file_id');
            $table->json('google_drive_raw_urls')->nullable()->after('google_drive_url');
            $table->string('google_drive_status', 32)->nullable()->default('pending')->after('google_drive_raw_urls');
            $table->timestamp('google_drive_synced_at')->nullable()->after('google_drive_status');
        });
    }

    public function down(): void
    {
        Schema::table('photo_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'google_drive_file_id',
                'google_drive_url',
                'google_drive_raw_urls',
                'google_drive_status',
                'google_drive_synced_at',
            ]);
        });
    }
};
