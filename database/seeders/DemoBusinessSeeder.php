<?php

namespace Database\Seeders;

use App\Actions\Businesses\CreateBusiness;
use App\Actions\Listings\CreateListingDraft;
use App\Actions\Listings\PublishListing;
use App\Actions\Listings\UpdateListing;
use App\Actions\Locations\SaveBusinessLocation;
use App\Enums\AcquisitionChannel;
use App\Enums\BusinessType;
use App\Enums\ContactMethod;
use App\Enums\Disclosure;
use App\Enums\EmployeeRange;
use App\Enums\FinancialMetric;
use App\Enums\LegalForm;
use App\Enums\LocationVisibility;
use App\Enums\LogisticsType;
use App\Enums\OnlineBusinessType;
use App\Enums\OperationType;
use App\Enums\PriceDisclosure;
use App\Enums\TechnologyPlatform;
use App\Enums\WebsiteVisibility;
use App\Models\Business;
use App\Models\Category;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A complete demo business with a published listing, owned by the local test user,
 * so the panel, the wizard in edit mode and the admin can be reviewed with real data.
 *
 * Goes through the same Actions as the wizard (events, slug, freshness clock included).
 * Idempotent: it does nothing when the business already exists. Requires the categories
 * and the Spanish geography (DatabaseSeeder runs both first).
 */
class DemoBusinessSeeder extends Seeder
{
    public const string BUSINESS_NAME = 'Tienda online de consumibles de impresoras';

    public const string OWNER_EMAIL = 'test@example.com';

    private const string SECTOR_SLUG = 'distribucion-y-mayoristas';

    private const string SUBSECTOR_SLUG = 'distribucion-y-mayoristas-productos-industriales';

    private const string PROVINCE_CODE = '12';

    private const string MUNICIPALITY_SLUG = 'borriana';

    public function run(): void
    {
        $owner = User::query()->firstOrCreate(
            ['email' => self::OWNER_EMAIL],
            ['name' => 'Test User', 'password' => bcrypt('password'), 'email_verified_at' => now()],
        );

        if (Business::query()->ownedBy($owner)->where('name', self::BUSINESS_NAME)->exists()) {
            return;
        }

        $sector = Category::query()->where('slug', self::SECTOR_SLUG)->first()
            ?? throw new RuntimeException('Run CategorySeeder before DemoBusinessSeeder.');
        $subsector = Category::query()->where('slug', self::SUBSECTOR_SLUG)->first();

        $municipality = Municipality::query()
            ->where('slug', self::MUNICIPALITY_SLUG)
            ->whereRelation('province', 'code', self::PROVINCE_CODE)
            ->first()
            ?? throw new RuntimeException('Run avytra:import-geography before DemoBusinessSeeder.');

        DB::transaction(function () use ($owner, $sector, $subsector, $municipality): void {
            $business = app(CreateBusiness::class)->handle($owner, $owner, [
                'business_type' => BusinessType::Hybrid->value,
                'category_id' => $sector->id,
                'subcategory_id' => $subsector?->id,
                'name' => self::BUSINESS_NAME,
                'legal_name' => 'Consumibles Plana Baixa, S.L.',
                'legal_form' => LegalForm::Sl->value,
                'show_legal_form' => false,
                'tagline' => 'Tóner, tinta y papel para empresas y particulares, con envío en 24 h y almacén propio en Borriana.',
                'description' => implode("\n\n", [
                    'Tienda online especializada en consumibles de impresión: cartuchos de tinta y tóner originales y compatibles, tambores, papel y pequeño material de oficina. Vende a particulares a través de la web y de marketplaces, y a empresas, gestorías y centros educativos de la provincia de Castellón con facturación mensual.',
                    'Abierta en 2024, ha cerrado su primer ejercicio completo con crecimiento sostenido y una base de más de 900 clientes recurrentes. Cuenta con un almacén de 180 m² en Borriana desde el que se prepara el envío el mismo día, un catálogo de más de 3.000 referencias con integración directa con dos mayoristas y una plantilla de tres personas que se mantiene tras la operación.',
                    'Se vende por la dedicación de la propietaria a otro proyecto empresarial. Se incluye la web, la marca, las cuentas en marketplaces, el stock actual valorado y el traspaso del alquiler del almacén. Se ofrece acompañamiento durante los tres primeros meses.',
                ]),
                'founded_year' => 2024,
                'employee_range' => EmployeeRange::ThreeToFive->value,
                'website_url' => 'https://consumibles-borriana.example',
                'website_visibility' => WebsiteVisibility::Private->value,
            ], [
                'online_business_type' => OnlineBusinessType::Ecommerce->value,
                'technology_platform' => TechnologyPlatform::Prestashop->value,
                'domain_registered_year' => 2024,
                'monthly_visits' => 18000,
                'monthly_visits_disclosure' => Disclosure::Exact->value,
                'registered_users' => 2400,
                'active_customers' => 900,
                'monthly_orders' => 650,
                'recurring_revenue_percent' => 35,
                'acquisition_channels' => [AcquisitionChannel::Seo->value, AcquisitionChannel::Sem->value, AcquisitionChannel::Marketplaces->value],
                'social_profiles' => [['network' => 'Instagram', 'url' => 'https://instagram.com/consumibles.borriana']],
                'sells_on_marketplaces' => ['Amazon', 'Miravia'],
                'has_stock' => true,
                'logistics_type' => LogisticsType::Own->value,
                'team_included' => true,
            ]);

            app(SaveBusinessLocation::class)->handle($business, [
                'province_id' => $municipality->province_id,
                'municipality_id' => $municipality->id,
                'postal_code' => '12530',
                'address_line' => "Camí d'Onda 14, nave 3",
                'location_visibility' => LocationVisibility::Approximate->value,
            ]);

            $listing = app(CreateListingDraft::class)->handle($business, $owner, [
                'primary_operation_type' => OperationType::FullSale->value,
                'operation_notes' => 'También se estudia la entrada de un socio con perfil comercial que asuma la gestión diaria.',
            ], [OperationType::FullSale, OperationType::PartnerEntry]);

            app(UpdateListing::class)->handle($listing, $owner, [
                'title' => 'Venta de tienda online de consumibles de impresoras en Borriana',
                'reason_for_sale' => 'La propietaria se incorpora a otro proyecto empresarial y no puede dedicarle el tiempo que requiere.',
                'highlights' => [
                    'Más de 900 clientes recurrentes y 650 pedidos al mes',
                    'Catálogo de 3.000 referencias integrado con dos mayoristas',
                    'Almacén propio en Borriana con envío el mismo día',
                    'Cuentas activas en Amazon y Miravia',
                    'Plantilla formada que continúa con el negocio',
                ],
                'includes_stock' => true,
                'includes_equipment' => true,
                'includes_property' => false,
                'includes_staff' => true,
                'includes_intellectual_property' => true,
                'included_assets_notes' => 'Web PrestaShop, dominio y marca, cuentas de marketplaces, estanterías y equipo de embalaje, stock valorado a fecha de firma.',
                'premises_is_rented' => true,
                'price_disclosure' => PriceDisclosure::Exact->value,
                'asking_price' => 95000,
                'is_price_negotiable' => true,
                'contact_name' => 'Marta Ribes',
                'preferred_contact_method' => ContactMethod::Email->value,
                'contact_email' => self::OWNER_EMAIL,
                'contact_phone' => '+34964000000',
                'contact_whatsapp' => '+34964000000',
                'contact_notes' => 'Llamadas de lunes a viernes de 9 a 14 h.',
            ], null, [
                FinancialMetric::AnnualRevenue->value => ['disclosure' => Disclosure::Exact->value, 'amount' => 240000, 'period_year' => 2025],
                FinancialMetric::AnnualProfit->value => ['disclosure' => Disclosure::Range->value, 'amount_min' => 30000, 'amount_max' => 40000, 'period_year' => 2025],
                FinancialMetric::MonthlyRent->value => ['disclosure' => Disclosure::Exact->value, 'amount' => 650],
                FinancialMetric::StockValue->value => ['disclosure' => Disclosure::OnRequest->value],
                FinancialMetric::Ebitda->value => ['disclosure' => Disclosure::Hidden->value, 'amount' => 38000, 'period_year' => 2025],
            ]);

            app(PublishListing::class)->handle($listing, $owner);
        });
    }
}
