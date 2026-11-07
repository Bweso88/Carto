<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiels : environnements, sites, éditeurs, domaines métier,
 * équipes et technologies (OS, langages, moteurs BDD… avec leur fin de support).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('environments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();          // PROD, PREPROD, RECETTE, DEV
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_production')->default(false);
            $table->timestamps();
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();         // Site primaire, DC Paris, AWS eu-west-3…
            $table->string('code', 30)->nullable()->unique();
            $table->string('kind', 30)->default('datacenter'); // datacenter | cloud_region | office
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();         // éditeurs logiciels et constructeurs
            $table->string('website')->nullable();
            $table->string('support_contact')->nullable();
            $table->timestamps();
        });

        Schema::create('business_domains', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();         // RH, Finance, Commercial…
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('email')->nullable();
            $table->string('lead_name')->nullable();
            $table->string('on_call_phone', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('technologies', function (Blueprint $table) {
            $table->id();
            $table->string('category', 20)->index();       // App\Enums\TechnologyCategory
            $table->string('name', 150);                   // MySQL, Debian, PHP…
            $table->string('version', 50)->default('');    // '' = version non précisée
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->date('end_of_support_at')->nullable(); // fin du support standard
            $table->date('end_of_life_at')->nullable();    // fin de vie définitive (EOL)
            $table->timestamps();

            $table->unique(['category', 'name', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technologies');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('business_domains');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('environments');
    }
};
