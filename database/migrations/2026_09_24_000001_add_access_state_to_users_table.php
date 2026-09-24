<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('ativa')->index();
        });

        DB::table('users')->where('role', 'admin')->update(['role' => 'administradora']);
        DB::table('users')->where('role', 'servidor')->update(['role' => 'equipe_tecnica']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'equipe_tecnica'");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('administradora', 'equipe_tecnica'))");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('ativa', 'inativa', 'pendente_mfa'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'servidor'");
        }

        DB::table('users')->where('role', 'administradora')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'equipe_tecnica')->update(['role' => 'servidor']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
