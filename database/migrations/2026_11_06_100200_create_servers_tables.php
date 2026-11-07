<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('hostname', 150)->unique();
            $table->string('fqdn')->nullable();
            $table->string('kind', 20)->index();                   // App\Enums\ServerKind
            $table->string('status', 20)->default('active')->index(); // App\Enums\ServerStatus
            $table->string('criticality', 20)->nullable()->index(); // App\Enums\Criticality (null = non évaluée)

            $table->foreignId('environment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('os_id')->nullable()->constrained('technologies')->nullOnDelete();
            $table->foreignId('owner_team_id')->nullable()->constrained('teams')->nullOnDelete();

            // Une VM pointe vers son hyperviseur / hôte physique
            $table->foreignId('host_id')->nullable()->constrained('servers')->nullOnDelete();

            // Matériel
            $table->foreignId('manufacturer_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('model', 150)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->unsignedSmallInteger('cpu_cores')->nullable();
            $table->unsignedInteger('ram_gb')->nullable();
            $table->unsignedInteger('storage_gb')->nullable();
            $table->date('commissioned_at')->nullable();
            $table->date('warranty_end_at')->nullable();

            $table->text('description')->nullable();
            $table->unsignedInteger('kerberos_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        // Plusieurs adresses par serveur (IPv4/IPv6, interfaces, VIP…)
        Schema::create('ip_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('address', 45)->index();
            $table->unsignedTinyInteger('version')->default(4);
            $table->string('label', 50)->nullable();               // eth0, admin, VIP…
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['server_id', 'address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_addresses');
        Schema::dropIfExists('servers');
    }
};
