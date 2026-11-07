<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('reader')->after('email')->index();
            // Traçabilité de la reprise depuis Kerberos
            $table->unsignedInteger('kerberos_id')->nullable()->unique();
            $table->string('kerberos_username', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'kerberos_id', 'kerberos_username']);
        });
    }
};
