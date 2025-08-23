<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // パスワードの後ろに role を追加（既存ユーザーは user とする）
            $table->string('role', 10)->default('user')->after('password');
            // もしユニーク制約など既にあるなら何も触らなくてOK
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
