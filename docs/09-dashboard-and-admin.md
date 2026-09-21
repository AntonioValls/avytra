# 09 — Panel de usuario, wizard y administración

## Panel de usuario (`/panel`)

Layout: el `layouts/app` con sidebar del starter kit, rebrandeado (ver [14-ui-design-system.md](14-ui-design-system.md)). La ruta `/dashboard` pasa a `/panel` manteniendo el nombre de ruta `dashboard`.

Navegación lateral: Inicio · Mis empresas · Mis publicaciones · Ajustes. (Superadmin ve además "Administración".)

### Inicio del panel

No es un dashboard de métricas. Responde a tres preguntas: qué tengo publicado, si está actualizado, qué debo hacer.

1. **Avisos accionables** (`flux:callout`): "Tu publicación *Traspaso panadería…* necesita confirmación. [Sigue disponible]". "Tu publicación se pausó por falta de confirmación. [Reactivar]". "Tienes un borrador sin terminar. [Continuar]".
2. **Mis publicaciones** (lista compacta con badge de estado y acciones).
3. **Mis empresas** (tarjetas).
4. Estado vacío para usuarios nuevos: "Aún no tienes empresas. [Crear mi primera empresa]" con explicación de 3 pasos.

### Mis empresas (`/panel/empresas`)

Tarjetas con: portada/logo, nombre, tipo (badge), sector, número de publicaciones (y estado de la activa si existe), fecha de última actualización. Acciones: Editar, Nueva publicación (si no hay activa), Ver publicación activa.

Formulario de empresa (`/panel/empresas/crear`, `/panel/empresas/{business}/editar`): componente Livewire con Form Object. Secciones: datos básicos, ubicación (si físico/híbrido), perfil online (si online/híbrido), imágenes. Se puede crear una empresa sin publicarla.

Nota UX: el wizard de publicación también crea la empresa cuando el usuario empieza por "Publicar" sin tener ninguna; el formulario de empresa independiente existe para editar y para usuarios que quieren preparar datos antes.

### Mis publicaciones (`/panel/publicaciones`)

Tabla (`flux:table` Pro) en escritorio, tarjetas en móvil. Columnas: título, empresa, estado (badge), última confirmación, próximo vencimiento, acciones.

Acciones por estado:

| Estado | Acciones |
|---|---|
| `draft` | Continuar (wizard), Eliminar |
| `published` | Ver, Editar, Sigue disponible (si necesita confirmación; siempre disponible), Pausar, Marcar como vendida |
| `paused` | Ver (privado), Editar, Reactivar, Marcar como vendida, Archivar |
| `expired` | Ver (privado), Editar, Reactivar (= confirmar), Marcar como vendida, Archivar |
| `sold` | Ver, Archivar, "Publicar de nuevo" (crea nueva publicación desde esta) |
| `suspended` | Ver motivo, Contactar con AVYTRA |
| `archived` | Ver (privado), "Publicar de nuevo" |

Confirmaciones destructivas (archivar, marcar vendida, eliminar borrador) con `flux:modal`.

## Wizard de publicación (`/panel/publicaciones/nueva`, `/panel/publicaciones/{listing}/editar`)

Un único componente Livewire de página (`pages::listings.wizard`) con propiedad `step`, Form Objects por paso y persistencia en base de datos **al completar cada paso** (el `Listing` se crea como `draft` en el paso 1; la `Business` se crea o selecciona en el paso 1). Así "abandonar y continuar" no requiere sesión ni estado en memoria.

Progreso: `flux:progress` o lista de pasos con estado (completado/actual/pendiente); en móvil, "Paso 3 de 8 · Características".

| Paso | Contenido | Componentes Flux | Validación mínima |
|---|---|---|---|
| 1. Empresa y operación | Seleccionar empresa existente o "Nueva"; tipo de negocio (`physical`/`online`/`hybrid`); tipo de operación | `flux:select`, `flux:radio.group variant="cards"` con icono y descripción | tipo de negocio, operación |
| 2. Información básica | Nombre comercial, sector, subsector, descripción corta, descripción completa, año, empleados | `flux:input`, `flux:select variant="listbox" searchable`, `flux:textarea` | nombre, sector |
| 3. Características | Motivo de la venta, puntos destacados (hasta 5), qué se incluye (checkboxes tri-estado), local alquiler/propiedad | `flux:textarea`, `flux:input` repetible, `flux:checkbox.group`, `flux:switch` | ninguna |
| 4. Información económica | Modo de precio + importes, negociable; facturación, beneficio, alquiler; "Añadir más datos" plegable con el resto | `flux:radio.group` (exacto/rango/consultar), `flux:input type=number` con sufijo €, `flux:select` para divulgación, `flux:accordion` | modo de precio coherente |
| 5. Ubicación / online | Físico/híbrido: provincia, municipio, dirección (privada), mapa con pin arrastrable, visibilidad. Online/híbrido: tipo online, plataforma, métricas opcionales | `flux:select searchable`, `flux:autocomplete` (municipio), `flux:radio.group` (visibilidad, con explicación de cada nivel), `flux:pillbox` (canales) | provincia y municipio (físico/híbrido); tipo online (online/híbrido) |
| 6. Contacto | Nombre de contacto, método preferido, canales; vista previa de cómo se verá el bloque de contacto | `flux:radio.group`, `flux:input`, `flux:phone` Pro | método preferido + canal coherente |
| 7. Imágenes | Portada, logo, galería con orden y alt | `flux:file-upload` Pro + `flux:file-item`, `wire:sort` para orden | ninguna (portada recomendada, aviso) |
| 8. Vista previa y publicación | Render de la ficha tal como se verá públicamente (misma vista parcial); checklist de campos que faltan; botón Publicar | `flux:callout`, `flux:button variant="primary"` | validación completa "listo para publicar" |

Reglas:

- Navegación libre entre pasos ya completados; "Guardar y salir" en todos.
- Los pasos 5 se adaptan al tipo (híbrido muestra ambos bloques).
- El título se sugiere en el paso 8 si está vacío: "{Operación} de {sector en minúsculas} en {municipio|provincia|online}".
- Prefill de contacto: nombre y email del usuario **se muestran como sugerencia editable** en el paso 6; nada se guarda hasta que el usuario avanza. Para publicaciones creadas por el superadmin, no hay prefill.
- Superadmin: el mismo wizard con un selector de propietario en el paso 1.
- Edición posterior: mismo componente en modo edición, acceso directo a cualquier paso.

## Administración (`/admin`)

Mismo layout de app con una sección de navegación "Administración" visible solo para superadmin y color de cabecera diferenciado (Ink) para distinguirlo del panel. Middleware `EnsureUserIsSuperadmin` (respuesta 404 para no revelar la existencia del panel).

| Ruta | Contenido |
|---|---|
| `/admin` | Resumen operativo: publicaciones que necesitan confirmación, pausadas automáticamente en los últimos 30 días, reportes abiertos, últimas publicaciones, últimos usuarios. Números y listas, no gráficos. |
| `/admin/usuarios` | Tabla con búsqueda; ver detalle (empresas y publicaciones); crear usuario en nombre de otra persona (nombre, email, teléfono; contraseña aleatoria; opción de enviar email de "establece tu contraseña"). |
| `/admin/empresas` | Tabla con filtros (propietario, tipo, sector); crear/editar con selector de propietario; cambiar propietario (con confirmación y audit). |
| `/admin/publicaciones` | Tabla con filtros por estado y condición (necesita confirmación, caducadas, suspendidas); acciones: publicar, pausar, reactivar, confirmar en nombre del propietario, marcar vendida, archivar, suspender (con motivo), levantar suspensión. Timeline de eventos (`flux:timeline` Pro). |
| `/admin/reportes` | Bandeja de reportes: abrir, ver publicación, resolver (con acción rápida: pausar/suspender/archivar) o descartar. |
| `/admin/auditoria` | Audit log filtrable por actor, acción, recurso. |

Búsqueda global con `flux:command` (Ctrl/Cmd+K) sobre usuarios, empresas y publicaciones: mejora de bajo coste, Phase 8 si el tiempo lo permite.

Toda acción administrativa sobre recursos ajenos registra `audit_logs` con `on_behalf_of_user_id` = propietario del recurso.

## Estados vacíos y errores

Cada listado tiene estado vacío con texto y acción. Los errores de validación se muestran inline con `flux:error`; los errores de transición de estado (p. ej. intentar publicar una empresa sin ubicación) se muestran como `flux:callout variant="danger"` con enlace al paso correspondiente.
