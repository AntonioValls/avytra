<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Sectors and subsectors of activity (docs/04-domain-model.md). Idempotent: keyed by slug,
 * so names and order can be adjusted and re-seeded without duplicating rows.
 */
class CategorySeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    private const array SECTORS = [
        'Hostelería y restauración' => [
            'Restaurantes',
            'Bares y cafeterías',
            'Pubs y ocio nocturno',
            'Comida rápida y para llevar',
            'Catering y eventos',
            'Hoteles, hostales y apartamentos turísticos',
        ],
        'Alimentación' => [
            'Panaderías y pastelerías',
            'Carnicerías y charcuterías',
            'Pescaderías',
            'Fruterías y verdulerías',
            'Supermercados y ultramarinos',
            'Tiendas gourmet y delicatessen',
        ],
        'Comercio' => [
            'Moda y calzado',
            'Hogar y decoración',
            'Electrónica e informática',
            'Librerías, papelerías y prensa',
            'Farmacias y parafarmacias',
            'Estancos y loterías',
            'Otros comercios',
        ],
        'Salud y bienestar' => [
            'Clínicas dentales',
            'Fisioterapia y rehabilitación',
            'Ópticas y audiología',
            'Gimnasios y centros deportivos',
            'Consultas y clínicas',
            'Residencias y centros de día',
        ],
        'Peluquería y estética' => [
            'Peluquerías',
            'Barberías',
            'Centros de estética y uñas',
            'Spa y masajes',
        ],
        'Automoción y transporte' => [
            'Talleres mecánicos',
            'Compraventa de vehículos',
            'Lavaderos y estaciones de servicio',
            'Transporte y logística',
            'Alquiler de vehículos',
        ],
        'Industria y fabricación' => [
            'Industria alimentaria',
            'Metal y mecanizado',
            'Textil y confección',
            'Madera y mueble',
            'Química, plásticos y envases',
            'Otras industrias',
        ],
        'Construcción e instalaciones' => [
            'Constructoras y reformas',
            'Fontanería, electricidad y climatización',
            'Carpintería y cerrajería',
            'Materiales de construcción',
        ],
        'Servicios profesionales' => [
            'Asesorías y gestorías',
            'Despachos de abogados',
            'Agencias inmobiliarias',
            'Consultoría',
            'Correduría de seguros',
            'Marketing y publicidad',
        ],
        'Educación y formación' => [
            'Academias y clases particulares',
            'Escuelas infantiles',
            'Centros de idiomas',
            'Formación profesional y online',
            'Autoescuelas',
        ],
        'Tecnología y digital' => [
            'Software y SaaS',
            'Tiendas online',
            'Marketing digital',
            'Servicios informáticos',
            'Medios y contenido digital',
            'Aplicaciones móviles',
        ],
        'Agricultura y ganadería' => [
            'Explotaciones agrícolas',
            'Explotaciones ganaderas',
            'Viveros y jardinería',
            'Bodegas y almazaras',
        ],
        'Turismo y ocio' => [
            'Agencias de viajes',
            'Actividades turísticas y de aventura',
            'Campings y casas rurales',
            'Ocio infantil y familiar',
            'Cines, salas y espectáculos',
        ],
        'Distribución y mayoristas' => [
            'Alimentación y bebidas',
            'Productos industriales',
            'Textil y calzado al por mayor',
            'Importación y exportación',
        ],
        'Servicios a empresas y hogares' => [
            'Limpieza',
            'Seguridad',
            'Mantenimiento y reparaciones',
            'Recursos humanos y trabajo temporal',
            'Lavanderías y tintorerías',
            'Mensajería y reparto',
        ],
        'Otros negocios' => [],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sectorOrder = 0;

        foreach (self::SECTORS as $sectorName => $subsectors) {
            $sector = Category::query()->updateOrCreate(
                ['slug' => Str::slug($sectorName)],
                ['parent_id' => null, 'name' => $sectorName, 'sort_order' => ++$sectorOrder, 'is_active' => true],
            );

            $subsectorOrder = 0;

            foreach ($subsectors as $subsectorName) {
                Category::query()->updateOrCreate(
                    ['slug' => Str::slug($sectorName.' '.$subsectorName)],
                    ['parent_id' => $sector->id, 'name' => $subsectorName, 'sort_order' => ++$subsectorOrder, 'is_active' => true],
                );
            }
        }
    }
}
