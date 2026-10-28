<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('queue_number')->comment('Nomor antrian');
            $table->string('name')->nullable()->comment('Nama pengunjung (opsional)');
            $table->enum('status', ['waiting', 'called', 'in_session', 'completed', 'skipped'])
                  ->default('waiting')
                  ->comment('Status antrian');
            $table->foreignId('template_id')->nullable()->constrained()->nullOnDelete()
                  ->comment('Template yang dipilih admin (opsional)');
            $table->string('session_token')->nullable()
                  ->comment('Token sesi foto bila sudah masuk sesi');
            $table->timestamp('called_at')->nullable()->comment('Waktu dipanggil');
            $table->timestamp('session_started_at')->nullable()->comment('Waktu mulai sesi foto');
            $table->timestamp('completed_at')->nullable()->comment('Waktu selesai');
            $table->timestamps();

            $table->index(['status', 'queue_number']);
        });

        // Tabel settings ringan untuk menyimpan konfigurasi antrian hari ini
        Schema::create('queue_settings', function (Blueprint $table) {
            $table->id();
            $table->date('active_date')->unique()->comment('Tanggal aktif');
            $table->unsignedInteger('last_number')->default(0)->comment('Nomor terakhir yang diambil');
            $table->unsignedInteger('current_serving')->default(0)->comment('Nomor yang sedang dilayani');
            $table->boolean('is_open')->default(false)->comment('Antrian dibuka/ditutup');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_settings');
        Schema::dropIfExists('queue_tickets');
    }
};
