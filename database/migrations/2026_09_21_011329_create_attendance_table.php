<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->date('date');
            $table->time('clock_in')->nullable(); // 出勤時間
            $table->time('clock_out')->nullable(); // 退勤時間
            $table->time('break_in')->nullable(); // 休憩開始
            $table->time('break_out')->nullable(); // 休憩終了
            $table->time('break_in_2')->nullable(); // 休憩2開始
            $table->time('break_out_2')->nullable(); // 休憩2終了
            $table->text('comment'); // 備考
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
