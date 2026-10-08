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
        Schema::create('attendance_corrects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')
                ->constrained('attendance_records')
                ->cascadeOnDelete();
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->string('memo')->nullable();
            $table->boolean('status');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
