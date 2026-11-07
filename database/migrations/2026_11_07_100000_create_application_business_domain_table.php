<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une application peut servir plusieurs domaines métier (ex. Finance ET Comptabilité).
 * La colonne applications.business_domain_id (un seul domaine) est remplacée par une table de liaison.
 * Les rattachements existants sont recopiés : aucune donnée n'est perdue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_business_domain', function (Blueprint $table) {
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_domain_id')->constrained()->cascadeOnDelete();
            $table->primary(['application_id', 'business_domain_id']);
            $table->index('business_domain_id');
        });

        DB::table('application_business_domain')->insertUsing(
            ['application_id', 'business_domain_id'],
            DB::table('applications')->whereNotNull('business_domain_id')->select('id', 'business_domain_id'),
        );

        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_domain_id');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('business_domain_id')->nullable()->after('handles_personal_data')->constrained()->nullOnDelete();
        });

        // Retour arrière : on garde un seul domaine par application (le premier par ordre d'identifiant)
        foreach (DB::table('application_business_domain')->orderBy('business_domain_id')->get() as $link) {
            DB::table('applications')->where('id', $link->application_id)->whereNull('business_domain_id')
                ->update(['business_domain_id' => $link->business_domain_id]);
        }

        Schema::dropIfExists('application_business_domain');
    }
};
