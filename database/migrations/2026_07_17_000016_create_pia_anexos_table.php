<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pia_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pia_id')->constrained('pias')->cascadeOnDelete();
            $table->string('nome_original');
            $table->string('descricao')->nullable();
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('tamanho')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pia_anexos');
    }
};
