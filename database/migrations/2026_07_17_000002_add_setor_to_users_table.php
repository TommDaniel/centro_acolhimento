<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('setor_id')->nullable()->constrained('setores')->nullOnDelete();
            $table->string('role')->default('servidor'); // admin | servidor
            $table->string('cargo')->nullable();
            $table->string('telefone')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('setor_id');
            $table->dropColumn(['role', 'cargo', 'telefone']);
        });
    }
};
