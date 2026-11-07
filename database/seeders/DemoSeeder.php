<?php

namespace Database\Seeders;

use App\Enums\ApplicationType as Type;
use App\Enums\Criticality as Crit;
use App\Enums\DataClassification;
use App\Enums\DeploymentRole as Role;
use App\Enums\FlowProtocol as Proto;
use App\Enums\ServerKind as Kind;
use App\Enums\TechnologyCategory as Cat;
use App\Models\Application;
use App\Models\ApplicationFlow;
use App\Models\BusinessDomain;
use App\Models\Environment;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
use App\Models\Technology;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * Jeu de démonstration inspiré des maquettes MeriMap. À ne pas lancer en production.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $prod = Environment::where('code', 'PROD')->firstOrFail();
        $dc1 = Site::firstOrCreate(['name' => 'Datacenter Paris DC1'], ['code' => 'PAR-DC1', 'kind' => 'datacenter']);
        $dc2 = Site::firstOrCreate(['name' => 'Datacenter Lyon DC2'], ['code' => 'LYS-DC2', 'kind' => 'datacenter']);

        $os = fn (string $name, string $version) => Technology::where(['category' => Cat::OperatingSystem, 'name' => $name, 'version' => $version])->value('id');
        $tech = fn (Cat $cat, string $name, string $version = '') => Technology::where(['category' => $cat, 'name' => $name, 'version' => $version])->value('id');

        $sre = Team::firstOrCreate(['name' => 'Platform SRE'], ['lead_name' => 'Marc V.', 'email' => 'sre@example.org', 'on_call_phone' => '+33 6 00 00 00 01']);
        $secops = Team::firstOrCreate(['name' => 'SecOps'], ['lead_name' => 'Elena R.', 'email' => 'secops@example.org']);
        $dba = Team::firstOrCreate(['name' => 'DBA'], ['lead_name' => 'Éléonore M.', 'email' => 'dba@example.org']);
        $finance = Team::firstOrCreate(['name' => 'Direction Financière'], ['lead_name' => 'Jean-Luc B.']);
        $digital = Team::firstOrCreate(['name' => 'Direction Digitale'], ['lead_name' => 'Nadia T.']);

        $dell = Vendor::firstOrCreate(['name' => 'Dell'])->id;
        $sap = Vendor::firstOrCreate(['name' => 'SAP'])->id;

        // --- Serveurs : 2 hyperviseurs physiques, des VMs, un serveur legacy ---
        $server = function (string $hostname, Kind $kind, array $extra = []) use ($prod, $dc1) {
            $ip = $extra['ip'] ?? null;
            unset($extra['ip']);
            $s = Server::updateOrCreate(['hostname' => $hostname], [
                'kind' => $kind, 'environment_id' => $prod->id, 'site_id' => $dc1->id, ...$extra,
            ]);
            if ($ip) {
                $s->ipAddresses()->updateOrCreate(['address' => $ip], ['is_primary' => true]);
            }

            return $s;
        };

        $hv1 = $server('esx-par-01', Kind::Physical, ['ip' => '10.10.0.11', 'manufacturer_id' => $dell, 'model' => 'PowerEdge R760', 'cpu_cores' => 64, 'ram_gb' => 768, 'criticality' => Crit::Critical, 'owner_team_id' => $sre->id]);
        $hv2 = $server('esx-par-02', Kind::Physical, ['ip' => '10.10.0.12', 'manufacturer_id' => $dell, 'model' => 'PowerEdge R760', 'cpu_cores' => 64, 'ram_gb' => 768, 'criticality' => Crit::Critical, 'owner_team_id' => $sre->id]);
        $hvLyon = $server('esx-lys-01', Kind::Physical, ['ip' => '10.20.0.11', 'site_id' => $dc2->id, 'manufacturer_id' => $dell, 'criticality' => Crit::High]);

        $web1 = $server('srv-web-01', Kind::Virtual, ['ip' => '10.10.1.21', 'host_id' => $hv1->id, 'os_id' => $os('Ubuntu', '22.04 LTS')]);
        $web2 = $server('srv-web-02', Kind::Virtual, ['ip' => '10.10.1.22', 'host_id' => $hv2->id, 'os_id' => $os('Ubuntu', '22.04 LTS')]);
        $app1 = $server('srv-app-prod-04', Kind::Virtual, ['ip' => '10.10.1.84', 'host_id' => $hv1->id, 'os_id' => $os('Red Hat Enterprise Linux', '9'), 'criticality' => Crit::Critical]);
        $db1 = $server('srv-db-primary-01', Kind::Virtual, ['ip' => '10.10.2.10', 'host_id' => $hv1->id, 'os_id' => $os('Debian', '12 Bookworm'), 'criticality' => Crit::Critical, 'owner_team_id' => $dba->id]);
        $db2 = $server('srv-db-replica-01', Kind::Virtual, ['ip' => '10.20.2.10', 'host_id' => $hvLyon->id, 'site_id' => $dc2->id, 'os_id' => $os('Debian', '12 Bookworm'), 'owner_team_id' => $dba->id]);
        $erp = $server('srv-erp-sap-01', Kind::Virtual, ['ip' => '10.10.3.10', 'host_id' => $hv2->id, 'os_id' => $os('Red Hat Enterprise Linux', '8'), 'criticality' => Crit::Critical]);
        $adServer = $server('srv-ad-01', Kind::Virtual, ['ip' => '10.10.0.50', 'host_id' => $hv2->id, 'os_id' => $os('Windows Server', '2022'), 'criticality' => Crit::Critical, 'owner_team_id' => $secops->id]);
        $legacy = $server('srv-legacy-backoffice-02', Kind::Virtual, ['ip' => '10.14.88.24', 'host_id' => $hv2->id, 'os_id' => $os('Debian', '9 Stretch'), 'description' => 'Back-office historique — migration prévue']);
        $mysql = $server('srv-mysql-03', Kind::Virtual, ['ip' => '10.14.92.102', 'host_id' => $hv2->id, 'os_id' => $os('Windows Server', '2012 R2')]);

        // --- Domaines métier ---
        $commerce = BusinessDomain::firstOrCreate(['name' => 'E-commerce'])->id;
        $fin = BusinessDomain::firstOrCreate(['name' => 'Finance'])->id;
        $compta = BusinessDomain::firstOrCreate(['name' => 'Comptabilité'])->id;
        $achats = BusinessDomain::firstOrCreate(['name' => 'Achats & Logistique'])->id;
        $si = BusinessDomain::firstOrCreate(['name' => 'Infrastructure & Sécurité'])->id;

        // --- Applications ---
        // Une application peut servir plusieurs domaines métier : clé « domains » des attributs
        $app = function (string $name, array $attrs, array $deploy, array $technologies = []) use ($prod) {
            $domains = $attrs['domains'] ?? [];
            unset($attrs['domains']);
            $a = Application::updateOrCreate(['name' => $name], $attrs);
            $a->businessDomains()->syncWithoutDetaching($domains);
            foreach ($deploy as [$srv, $role]) {
                $a->deployments()->updateOrCreate(
                    ['server_id' => $srv->id, 'role' => $role],
                    ['environment_id' => $prod->id],
                );
            }
            $a->technologies()->syncWithoutDetaching(array_filter($technologies));

            return $a;
        };

        $ad = $app('Active Directory', ['type' => Type::Technical, 'criticality' => Crit::Critical, 'domains' => [$si], 'technical_owner_team_id' => $secops->id, 'vendor_id' => Vendor::where('name', 'Microsoft')->value('id')], [[$adServer, Role::Application]]);
        $sso = $app('SSO Identity Gateway', ['type' => Type::Technical, 'criticality' => Crit::Critical, 'domains' => [$si], 'description' => 'Fédération OIDC / SAML2, MFA', 'technical_owner_team_id' => $secops->id, 'sla_target' => 99.99], [[$web2, Role::Application]]);
        $orders = $app('Service Commandes & Panier', ['criticality' => Crit::Critical, 'domains' => [$commerce, $achats], 'technical_owner_team_id' => $sre->id, 'business_owner_team_id' => $digital->id, 'handles_personal_data' => true, 'data_classification' => DataClassification::Confidential, 'sla_target' => 99.95, 'rto_minutes' => 15, 'rpo_minutes' => 1],
            [[$app1, Role::Application], [$db1, Role::Database]], [$tech(Cat::Language, 'Java', '21'), $tech(Cat::Database, 'PostgreSQL', '16')]);
        $shop = $app('Plateforme E-Commerce Core', ['criticality' => Crit::Critical, 'domains' => [$commerce], 'description' => 'Catalogue produits, panier et checkout', 'technical_owner_team_id' => $sre->id, 'business_owner_team_id' => $digital->id, 'handles_personal_data' => true, 'sla_target' => 99.99, 'user_base' => 'Clients web et mobile', 'user_count' => 18500],
            [[$web1, Role::Web], [$web2, Role::Web]], [$tech(Cat::Language, 'JavaScript / TypeScript')]);
        $payment = $app('Passerelle Paiement & Billing', ['criticality' => Crit::Critical, 'domains' => [$fin, $commerce], 'description' => 'Gateway PCI-DSS de tokenisation, routage SEPA / CB', 'technical_owner_team_id' => $secops->id, 'business_owner_team_id' => $finance->id, 'data_classification' => DataClassification::Secret, 'sla_target' => 99.99],
            [[$app1, Role::Application]], [$tech(Cat::Language, 'Java', '21')]);
        $erpApp = $app('ERP SAP S/4HANA', ['criticality' => Crit::Critical, 'domains' => [$fin, $compta, $achats], 'vendor_id' => $sap, 'description' => 'Stocks, facturation groupe et comptabilité', 'business_owner_team_id' => $finance->id, 'sla_target' => 99.9],
            [[$erp, Role::Application]]);
        $backoffice = $app('Back-office historique', ['criticality' => Crit::Medium, 'domains' => [$commerce], 'description' => 'Application legacy à migrer'],
            [[$legacy, Role::Application], [$mysql, Role::Database]], [$tech(Cat::Language, 'PHP', '7.4'), $tech(Cat::Database, 'MySQL', '5.7')]);
        $billingBatch = $app('Facturation récurrente', ['criticality' => Crit::High, 'domains' => [$fin, $compta], 'business_owner_team_id' => $finance->id],
            [[$app1, Role::Batch]]);

        // --- Flux : source consomme cible ---
        $flow = function (Application $from, Application $to, Proto $protocol, array $extra = []) {
            ApplicationFlow::updateOrCreate(
                ['source_application_id' => $from->id, 'target_application_id' => $to->id, 'protocol' => $protocol],
                ['is_encrypted' => true, 'is_critical' => true, ...$extra],
            );
        };
        $flow($shop, $orders, Proto::Rest, ['port' => 443]);
        $flow($shop, $sso, Proto::Sso);
        $flow($orders, $payment, Proto::Rest, ['port' => 443]);
        $flow($orders, $erpApp, Proto::Soap, ['is_critical' => false, 'frequency' => 'Toutes les 15 min']);
        $flow($billingBatch, $orders, Proto::DatabaseLink, ['frequency' => 'Batch nocturne']);
        $flow($billingBatch, $erpApp, Proto::FileTransfer, ['frequency' => 'Quotidien']);
        $flow($sso, $ad, Proto::Ldap, ['port' => 636]);
        $flow($erpApp, $ad, Proto::Ldap, ['port' => 636]);
        // Flux non chiffré : à remonter en gouvernance
        $flow($backoffice, $erpApp, Proto::Rest, ['port' => 8080, 'is_encrypted' => false, 'is_critical' => false, 'description' => 'HTTP en clair — violation PSSI']);
    }
}
