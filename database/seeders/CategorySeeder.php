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
     * Introduction of each sector page (docs/15, "textos reales de categorías"): shown under
     * the title and used as meta description. Spanish on purpose: it is content, not UI.
     *
     * @var array<string, string>
     */
    private const array DESCRIPTIONS = [
        'Hostelería y restauración' => 'Restaurantes, bares, cafeterías, locales de ocio y alojamientos en venta o traspaso. Negocios en marcha con licencia, clientela y equipamiento, publicados por sus propietarios con disponibilidad confirmada.',
        'Alimentación' => 'Panaderías, carnicerías, fruterías, supermercados y tiendas de alimentación que cambian de manos. Comercios de proximidad con clientela fija, en traspaso o venta directa por el propietario.',
        'Comercio' => 'Tiendas de moda, hogar, electrónica, librerías, farmacias, estancos y otros comercios en venta o traspaso. Locales con fondo de comercio, stock y clientela, con los datos que el vendedor decide hacer públicos.',
        'Salud y bienestar' => 'Clínicas dentales, centros de fisioterapia, ópticas, gimnasios, consultas y residencias en venta o traspaso. Negocios sanitarios y de bienestar con licencia de actividad y cartera de pacientes o socios.',
        'Peluquería y estética' => 'Peluquerías, barberías, centros de estética, uñas, spa y masajes en traspaso o venta. Locales equipados y con clientela habitual, ideales para profesionales que quieren emprender por su cuenta.',
        'Automoción y transporte' => 'Talleres mecánicos, concesionarios y compraventas, lavaderos, gasolineras, empresas de transporte y alquiler de vehículos en venta o traspaso, con maquinaria, licencias y flota cuando procede.',
        'Industria y fabricación' => 'Empresas industriales en venta: alimentaria, metal, textil, madera, química y plásticos. Fábricas y talleres con maquinaria, instalaciones y cartera de clientes, para continuar la actividad o integrarla en otro grupo.',
        'Construcción e instalaciones' => 'Constructoras, empresas de reformas, instaladores de fontanería, electricidad y climatización, carpinterías, cerrajerías y almacenes de materiales en venta o traspaso.',
        'Servicios profesionales' => 'Asesorías, gestorías, despachos, agencias inmobiliarias, consultoras, corredurías de seguros y agencias de marketing en venta. Carteras de clientes y equipos consolidados que buscan continuidad.',
        'Educación y formación' => 'Academias, escuelas infantiles, centros de idiomas, formación profesional y autoescuelas en venta o traspaso. Centros con alumnado, autorizaciones y aulas equipadas.',
        'Tecnología y digital' => 'Empresas de software y SaaS, tiendas online, agencias de marketing digital, servicios informáticos, medios digitales y aplicaciones en venta. Negocios que funcionan total o parcialmente en internet.',
        'Agricultura y ganadería' => 'Explotaciones agrícolas y ganaderas, viveros, empresas de jardinería, bodegas y almazaras en venta. Fincas, instalaciones y marcas con producción en marcha.',
        'Turismo y ocio' => 'Agencias de viajes, empresas de actividades turísticas y de aventura, campings, casas rurales, ocio infantil y salas de espectáculos en venta o traspaso.',
        'Distribución y mayoristas' => 'Distribuidoras y mayoristas de alimentación y bebidas, productos industriales, textil y calzado, importación y exportación en venta. Almacenes, rutas y carteras de clientes establecidas.',
        'Servicios a empresas y hogares' => 'Empresas de limpieza, seguridad, mantenimiento, recursos humanos, lavanderías, mensajería y reparto en venta o traspaso. Servicios recurrentes con contratos y clientela estable.',
        'Otros negocios' => 'Negocios y empresas en venta o traspaso que no encajan en los sectores anteriores. Cada publicación explica la actividad, lo que se incluye y cómo contactar con el propietario.',
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
                ['parent_id' => null, 'name' => $sectorName, 'description' => self::DESCRIPTIONS[$sectorName], 'sort_order' => ++$sectorOrder, 'is_active' => true],
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
