<?php

namespace Database\Seeders;

use App\Enums\TechnologyCategory as Cat;
use App\Models\Environment;
use App\Models\Technology;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * Référentiels de base. Idempotent : peut être relancé en production.
 *
 * Les dates de fin de support sont indicatives (sources éditeurs, état fin 2026) :
 * à faire valider par vos équipes avant de s'en servir pour un audit.
 */
class ReferenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['PROD', 'Production', 10, true],
            ['PREPROD', 'Préproduction', 20, false],
            ['RECETTE', 'Recette', 30, false],
            ['DEV', 'Développement', 40, false],
        ] as [$code, $name, $order, $isProd]) {
            Environment::updateOrCreate(['code' => $code], [
                'name' => $name, 'sort_order' => $order, 'is_production' => $isProd,
            ]);
        }

        $ms = Vendor::firstOrCreate(['name' => 'Microsoft'])->id;
        $oracle = Vendor::firstOrCreate(['name' => 'Oracle'])->id;
        $canonical = Vendor::firstOrCreate(['name' => 'Canonical'])->id;
        $redhat = Vendor::firstOrCreate(['name' => 'Red Hat'])->id;

        // [catégorie, nom, version, éditeur, fin de support, fin de vie]
        $catalog = [
            [Cat::OperatingSystem, 'Windows Server', '2012 R2', $ms, '2018-10-09', '2023-10-10'],
            [Cat::OperatingSystem, 'Windows Server', '2016', $ms, '2022-01-11', '2027-01-12'],
            [Cat::OperatingSystem, 'Windows Server', '2019', $ms, '2024-01-09', '2029-01-09'],
            [Cat::OperatingSystem, 'Windows Server', '2022', $ms, '2026-10-13', '2031-10-14'],
            [Cat::OperatingSystem, 'Ubuntu', '20.04 LTS', $canonical, '2025-05-31', null],
            [Cat::OperatingSystem, 'Ubuntu', '22.04 LTS', $canonical, '2027-06-01', null],
            [Cat::OperatingSystem, 'Ubuntu', '24.04 LTS', $canonical, '2029-05-31', null],
            [Cat::OperatingSystem, 'Debian', '9 Stretch', null, '2020-07-18', '2022-06-30'],
            [Cat::OperatingSystem, 'Debian', '12 Bookworm', null, '2026-06-10', '2028-06-30'],
            [Cat::OperatingSystem, 'Red Hat Enterprise Linux', '7', $redhat, '2019-08-06', '2024-06-30'],
            [Cat::OperatingSystem, 'Red Hat Enterprise Linux', '8', $redhat, '2024-05-31', '2029-05-31'],
            [Cat::OperatingSystem, 'Red Hat Enterprise Linux', '9', $redhat, '2027-05-31', '2032-05-31'],
            [Cat::Database, 'MySQL', '5.7', $oracle, null, '2023-10-31'],
            [Cat::Database, 'MySQL', '8.0', $oracle, null, '2026-04-30'],
            [Cat::Database, 'MySQL', '8.4 LTS', $oracle, null, '2032-04-30'],
            [Cat::Database, 'PostgreSQL', '12', null, null, '2024-11-21'],
            [Cat::Database, 'PostgreSQL', '16', null, null, '2028-11-09'],
            [Cat::Database, 'SQL Server', '2014', $ms, '2019-07-09', '2024-07-09'],
            [Cat::Database, 'SQL Server', '2019', $ms, '2025-02-28', '2030-01-08'],
            [Cat::Database, 'Oracle Database', '19c', $oracle, null, null],
            [Cat::Language, 'PHP', '7.4', null, '2021-11-28', '2022-11-28'],
            [Cat::Language, 'PHP', '8.3', null, '2025-12-31', '2027-12-31'],
            [Cat::Language, 'Java', '17', $oracle, null, null],
            [Cat::Language, 'Java', '21', $oracle, null, null],
            [Cat::Language, 'C#', '', $ms, null, null],
            [Cat::Language, 'Python', '3.12', null, null, '2028-10-31'],
            [Cat::Language, 'JavaScript / TypeScript', '', null, null, null],
        ];

        foreach ($catalog as [$cat, $name, $version, $vendor, $eos, $eol]) {
            Technology::updateOrCreate(
                ['category' => $cat, 'name' => $name, 'version' => $version],
                ['vendor_id' => $vendor, 'end_of_support_at' => $eos, 'end_of_life_at' => $eol],
            );
        }
    }
}
