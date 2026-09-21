# 14 — Sistema de diseño e identidad aplicada

Fuente de verdad: `docs/design-system/AVYTRA_Brand_Identity_v1.pdf` y `AVYTRA_design_tokens.json`. Este documento traduce el manual a Flux UI + Tailwind 4. No se altera la identidad sin justificarlo en `DECISIONS.md`.

## Identidad

- **Personalidad:** clara, ambiciosa, humana, precisa. "Tecnología sin frialdad; negocio sin burocracia."
- **Dirección visual:** minimalismo nórdico, alto contraste, superficies claras, bloques redondeados, flechas de continuidad. Fotografía documental de negocios reales; nunca apretones de manos de stock.
- **UI Foundations (manual, p. 12):** "cercano a una plataforma financiera moderna, no a un clasificado. Mucho espacio, jerarquía fuerte, datos comparables y acciones inequívocas."
- **Regla del manual:** si un recurso visual no mejora comprensión o reconocimiento, no se añade.

## Paleta y uso

| Token | Hex | Rol según manual | Uso en UI |
|---|---|---|---|
| Ink | `#101828` | Base / confianza | Texto principal, cabeceras oscuras, botones secundarios "sólidos", fondo de la administración, footer |
| Lime | `#B8F34A` | Acción / progreso | **Solo** acciones primarias, estados de éxito/publicada, progreso del wizard, acentos puntuales. Nunca fondos grandes ni texto sobre blanco (contraste insuficiente): el texto sobre Lime es Ink. |
| Transfer Blue | `#4E6BFF` | Interacción / enlace | Enlaces, estados informativos, foco, badges de tipo de operación, círculo de zona aproximada en el mapa |
| Mist | `#F5F7FA` | Fondos / superficie | Fondo de página, tarjetas secundarias, sidebar del panel |
| Slate | `#667085` | Texto secundario | Metadatos, ayudas, placeholders |
| White | `#FFFFFF` | — | Superficies principales, tarjetas |

Estados semánticos (no están en el manual; se toman de Tailwind y se documentan aquí): éxito = Lime/`green-600` para texto, aviso = `amber-500`, error = `red-600`, neutro = `zinc`. Se usan solo en badges, callouts y validación.

Contraste: Ink sobre White 16.4:1; Ink sobre Lime alto. Texto Slate sobre White 4.7:1 (AA). No usar Lime como color de texto sobre fondo claro.

### Mapeo a Flux / Tailwind (`resources/css/app.css`)

```css
@theme {
    --font-sans: 'Lato', ui-sans-serif, system-ui, sans-serif;

    --color-ink: #101828;
    --color-lime: #B8F34A;
    --color-transfer: #4E6BFF;
    --color-mist: #F5F7FA;
    --color-slate: #667085;

    /* Flux accent = acción primaria */
    --color-accent: var(--color-lime);
    --color-accent-content: var(--color-ink);      /* texto/iconos "accent" sobre blanco → Ink, no Lime */
    --color-accent-foreground: var(--color-ink);   /* texto sobre botón accent */

    --radius-sm: 12px; --radius-md: 18px; --radius-lg: 24px;  /* tokens del manual */
}
```

Nota sobre `--color-accent-content`: Flux lo usa para enlaces y texto "accent" (por ejemplo `flux:link`). Lime como texto sobre blanco no cumple contraste, por eso se mapea a Ink y los enlaces de contenido usan Transfer Blue explícitamente (`text-transfer`). Se verificará en Phase 1 el comportamiento real de Flux 2.20 con estos tokens y se ajustará el mapeo si algún componente queda ilegible.

Modo oscuro: **no** en el MVP público. La marca es luminosa. El starter kit trae selector de apariencia (`settings/appearance`); se mantiene para el panel con paleta oscura basada en Ink, pero la parte pública fuerza modo claro. Se elimina el `class="dark"` fijo del layout.

## Tipografía

Lato (Google Fonts o Bunny Fonts, self-host en Phase 10 si se prefiere): Heavy (900) para titulares, Regular (400), Medium (500), Semibold (600) para interfaz. Escala del manual:

| Uso | Tamaño / interlineado | Peso |
|---|---|---|
| Título hero | 44 / 48 (móvil 32 / 36) | Heavy |
| Título de página | 30 / 36 | Heavy |
| Título de sección / tarjeta | 20 / 28 | Semibold |
| Texto | 16 / 24 | Regular |
| Etiqueta | 12 / 16, tracking +8 % (`tracking-wider uppercase`) | Semibold |

`@fonts` de Flux carga Inter por defecto; se sustituye por el `<link>` de Lato en `partials/head.blade.php` y se declara `--font-sans` en el tema.

## Logotipo

- `avytra-logo-primary.svg` (Ink + flecha Lime) sobre fondos claros: cabecera pública, emails.
- `avytra-logo-white.svg` sobre Ink: footer, cabecera de administración.
- `avytra-symbol.svg`: favicon, sidebar colapsado, placeholder de imágenes, marcador de mapa.
- `avytra-app-icon.svg`: `apple-touch-icon`, PWA futura.
- Área de seguridad: ½ de la altura del símbolo. Tamaño mínimo digital 96 px (logo), 24 px (símbolo).
- Prohibido: cambiar el color de la flecha, estirar, sombras/contornos/degradados.

Implementación: los SVG se copian a `resources/svg/` y se exponen mediante los componentes existentes `x-app-logo` y `x-app-logo-icon` (se reescriben con el SVG real). Favicon y `apple-touch-icon.png` en `public/` se regeneran desde `avytra-app-icon.svg`.

## Lenguaje gráfico

Flechas y líneas de continuidad como elemento secundario: en "Cómo funciona", en el progreso del wizard (paso → paso), en el CTA "Ver oportunidad →". Nunca compiten con el contenido. Iconos: Heroicons (incluidos en Flux), trazo 2 px, 24 px; para acciones de marca (transferir, validado) se usan Heroicons semánticamente cercanos (`arrow-right-circle`, `check-badge`).

## Tres contextos, una marca

| Contexto | Layout | Diferenciación |
|---|---|---|
| Marketplace público | `layouts/public` (nuevo): header blanco con logo primario, navegación mínima (Explorar, Publicar, Entrar), footer Ink | Mucho aire, fotografía, tarjetas blancas sobre Mist |
| Panel de usuario | `layouts/app` (starter kit, rebrandeado): sidebar Mist, contenido blanco | Funcional, formularios Flux, callouts accionables |
| Administración | Mismo `layouts/app` con `variant="admin"`: sidebar Ink con logo blanco y etiqueta "Administración" | Tablas densas, más datos por pantalla, claramente distinto para no confundir contextos |

## Componentes Flux por caso de uso

| Necesidad | Flux | Notas |
|---|---|---|
| Botón primario | `flux:button variant="primary"` | Lime con texto Ink |
| Botón secundario | `flux:button` (outline) / `variant="ghost"` | |
| Botón peligroso | `flux:button variant="danger"` | Archivar, suspender |
| Tarjetas | `flux:card` | Radio 18 px |
| Badges de estado | `flux:badge` con `color` por enum | Ver tabla en 06 |
| Formularios | `flux:field`, `flux:label`, `flux:input`, `flux:textarea`, `flux:select`, `flux:radio.group`, `flux:checkbox`, `flux:switch`, `flux:description`, `flux:error` | |
| Selector con búsqueda | `flux:select variant="listbox" searchable` | Provincias, sectores |
| Autocompletar | `flux:autocomplete` (Pro) | Municipios |
| Teléfono | `flux:phone` (Pro) | E.164 |
| Multi-selección | `flux:pillbox` (Pro) | Canales |
| Subida de archivos | `flux:file-upload` + `flux:file-item` (Pro) | Imágenes |
| Tablas | `flux:table` (Pro) | Panel y admin |
| Modales | `flux:modal` (`variant="flyout"` para filtros móvil) | |
| Notificaciones | `flux:toast` | Ya en layout |
| Avisos | `flux:callout` | Acciones pendientes |
| Pestañas | `flux:tabs` (Pro) | Ficha admin |
| Progreso wizard | `flux:progress` + lista de pasos propia | |
| Paginación | `flux:pagination` | |
| Breadcrumbs | `flux:breadcrumbs` | |
| Acordeón | `flux:accordion` (Pro) | "Más datos económicos" |
| Timeline | `flux:timeline` (Pro) | Eventos de la publicación en admin |
| Command palette | `flux:command` (Pro) | Búsqueda global admin |
| Skeleton | `flux:skeleton` | Carga de listados |

Componentes propios permitidos (Blade, sin JS nuevo): `x-listing-card`, `x-price`, `x-freshness-badge`, `x-map.listing`, `x-public.header`, `x-public.footer`, `x-empty-state`, `x-section-heading`. Antes de crear otro, comprobar si Flux ya lo cubre.

## Responsive y móvil

- Mobile-first. Puntos de corte de Tailwind por defecto.
- Objetivos táctiles ≥ 44 px; barra inferior fija "Contactar" en la ficha móvil.
- Filtros en flyout en móvil.
- Imágenes con `srcset` y tamaños (thumbnail/card/detail) — ver [17-media-strategy.md](17-media-strategy.md).
- Wizard: un campo por fila en móvil; navegación "Anterior / Siguiente" fija abajo.

## Accesibilidad

- Contraste AA en todo texto; Lime nunca como texto.
- Todos los formularios con `flux:label` (nunca placeholder como única etiqueta).
- Foco visible (anillo Transfer Blue).
- `alt` obligatorio en imágenes de galería (editable; por defecto "{nombre}, imagen N").
- Mapa con alternativa textual.
- Sin animaciones que no respeten `prefers-reduced-motion`.

## Voz en la interfaz

Microcopys directos, en segundo persona de cortesía neutra ("tú"). Sin exclamaciones comerciales. Ejemplos:

- Botón de vigencia: "Sigue disponible".
- Estado vacío: "Aún no tienes publicaciones. Cuando publiques tu primera empresa aparecerá aquí."
- Visibilidad de ubicación: "Zona aproximada (recomendado): mostramos un área de unos 700 m; nadie verá tu dirección exacta."
