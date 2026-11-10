<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * マイグレーション実行時の処理
     */
    public function up(): void
    {
        // 既存のattendance_recordsテーブルを変更する
        Schema::table('attendance_records', function (Blueprint $table) {

            // 勤怠データの備考を保存するcommentカラムを追加する
            $table->text('comment')->nullable();

        });
    }

    /**
     * マイグレーションを元に戻す処理
     */
    public function down(): void
    {
        // 既存のattendance_recordsテーブルを変更する
        Schema::table('attendance_records', function (Blueprint $table) {

            // 追加したcommentカラムを削除する
            $table->dropColumn('comment');

        });
    }
};