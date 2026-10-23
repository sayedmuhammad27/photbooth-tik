<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambah kolom untuk galeri template (kategori, tag, warna latar, urutan).
     * Hanya MENAMBAH kolom: data template yang sudah ada tidak berubah.
     */
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->string('category', 30)->default('umum')->after('description')->index();
            $table->json('tags')->nullable()->after('category');
            $table->string('bg_color', 7)->default('#131947')->after('frame_asset');
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'tags', 'bg_color', 'sort_order']);
        });
    }
};
