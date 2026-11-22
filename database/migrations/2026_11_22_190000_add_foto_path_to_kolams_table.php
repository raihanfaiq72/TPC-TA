<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kolams', function (Blueprint $table): void {
            $table->string('foto_path')->nullable()->after('nama');
        });
    }

    public function down(): void
    {
        Schema::table('kolams', function (Blueprint $table): void {
            $table->dropColumn('foto_path');
        });
    }
};
