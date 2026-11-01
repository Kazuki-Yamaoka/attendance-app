<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_correction_breaktimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_correction_id')->constrained('attendance_corrections', 'id', 'attendance_correction_breaks_attendance_correction_id_foreign')->onDelete('cascade');
            $table->datetime('break_in');
            $table->datetime('break_out');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_correction_breaktimes');
    }
};
