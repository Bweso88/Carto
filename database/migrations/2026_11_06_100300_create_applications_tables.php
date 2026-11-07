<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();                   // APP-0001, généré automatiquement
            $table->string('name', 200)->index();
            $table->text('description')->nullable();
            $table->string('type', 20)->default('business')->index();       // App\Enums\ApplicationType
            $table->string('lifecycle_status', 20)->default('production')->index(); // App\Enums\LifecycleStatus
            $table->string('criticality', 20)->nullable()->index();          // App\Enums\Criticality
            $table->string('data_classification', 20)->nullable();           // App\Enums\DataClassification
            $table->boolean('handles_personal_data')->default(false);        // RGPD

            $table->foreignId('business_domain_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('business_owner_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('technical_owner_team_id')->nullable()->constrained('teams')->nullOnDelete();

            $table->string('user_base')->nullable();                 // « Utilisateurs » de Kerberos (texte libre)
            $table->unsignedInteger('user_count')->nullable();
            $table->decimal('sla_target', 6, 3)->nullable();         // 99.900
            $table->unsignedInteger('rto_minutes')->nullable();      // durée max d'interruption
            $table->unsignedInteger('rpo_minutes')->nullable();      // perte de données max
            $table->string('documentation_url')->nullable();

            $table->unsignedInteger('kerberos_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        // Où tourne l'application : N serveurs, par environnement et par rôle
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('environment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 20)->default('application');      // App\Enums\DeploymentRole
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'server_id', 'role']);
            $table->index('server_id');
        });

        // Pile technique : langages, frameworks, moteurs BDD, middlewares
        Schema::create('application_technology', function (Blueprint $table) {
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technology_id')->constrained()->cascadeOnDelete();
            $table->primary(['application_id', 'technology_id']);
        });

        // Flux / dépendances orientés : la source consomme la cible
        Schema::create('application_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('target_application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('protocol', 20)->default('other');        // App\Enums\FlowProtocol
            $table->unsignedInteger('port')->nullable();
            $table->boolean('is_encrypted')->nullable();             // null = inconnu
            $table->boolean('is_critical')->default(true);           // la source tombe si la cible tombe
            $table->string('frequency', 50)->nullable();             // temps réel, batch quotidien…
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['source_application_id', 'target_application_id', 'protocol'], 'flows_unique');
            $table->index('target_application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_flows');
        Schema::dropIfExists('application_technology');
        Schema::dropIfExists('deployments');
        Schema::dropIfExists('applications');
    }
};
