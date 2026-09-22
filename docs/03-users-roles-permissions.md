# 03 — Usuarios, roles y permisos

## Roles

Dos roles en el MVP, modelados como enum PHP `App\Enums\UserRole` almacenado en la columna `users.role`:

| Enum | Valor | Descripción |
|---|---|---|
| `UserRole::User` | `user` | Usuario registrado normal. Valor por defecto. |
| `UserRole::Superadmin` | `superadmin` | Acceso global. Asignado manualmente por comando Artisan. |

**No se instala ningún paquete de roles/permisos** (spatie/laravel-permission u otros). Con dos roles y permisos derivados de la propiedad, una columna enum y Policies cubren el 100 % de los casos. Si en el futuro aparecen roles intermedios (moderador, asesor), se evaluará un paquete en un ADR. Ver `DECISIONS.md` ADR-002.

El rol superadmin se asigna con un comando Artisan (`php artisan avytra:superadmin {email}`), nunca desde la UI ni desde el registro. Se recomienda que la cuenta superadmin tenga 2FA activado (Fortify ya lo soporta).

## Propiedad y autoría

Todo recurso principal registra tres relaciones con `users`:

| Campo | Significado | Aplica a |
|---|---|---|
| `owner_user_id` | Propietario del recurso. Es quien tiene permisos de gestión como usuario normal. | `businesses` (los `listings` heredan el propietario a través de `business`) |
| `created_by_user_id` | Usuario que creó físicamente el registro. Puede ser el superadmin. | `businesses`, `listings` |
| `updated_by_user_id` | Último usuario que modificó el registro. | `businesses`, `listings` |

Esto permite que el superadmin cree una empresa para otra persona (`owner_user_id` = la persona, `created_by_user_id` = superadmin) sin convertirse en su propietario. Un `Listing` no tiene `owner_user_id` propio: su propietario es siempre `listing->business->owner`. Evita inconsistencias (una publicación cuya empresa es de otro usuario).

Los campos `created_by/updated_by` se rellenan automáticamente mediante un trait `App\Models\Concerns\TracksAuthorship` (observers `creating`/`updating` usando `auth()->id()`), sin lógica en componentes. Cuando la acción la ejecuta el scheduler o un comando sin usuario, quedan `null`.

Además, las acciones administrativas relevantes se registran en `audit_logs` (ver [16-security-and-privacy.md](16-security-and-privacy.md) y [07-database-design.md](07-database-design.md)).

## Matriz de permisos

Leyenda: ✅ permitido · ❌ denegado · 🔒 solo si es propietario

### Empresas (`Business`)

| Acción | Invitado | Usuario | Superadmin |
|---|---|---|---|
| Ver ficha pública (a través de un `Listing` publicado) | ✅ | ✅ | ✅ |
| Listar mis empresas | ❌ | 🔒 | ✅ (todas) |
| Ver detalle interno | ❌ | 🔒 | ✅ |
| Crear | ❌ | ✅ (propietario = yo) | ✅ (propietario = cualquiera) |
| Editar | ❌ | 🔒 | ✅ |
| Cambiar propietario | ❌ | ❌ | ✅ |
| Eliminar | ❌ | 🔒 solo si no tiene publicaciones publicadas/vendidas | ✅ |
| Gestionar imágenes | ❌ | 🔒 | ✅ |

### Publicaciones (`Listing`)

| Acción | Invitado | Usuario | Superadmin |
|---|---|---|---|
| Ver publicada | ✅ | ✅ | ✅ |
| Ver no publicada (borrador, pausada, vendida, archivada, suspendida) | ❌ | 🔒 | ✅ |
| Crear para una empresa | ❌ | 🔒 (empresa propia) | ✅ |
| Editar | ❌ | 🔒 salvo `archived`/`suspended` | ✅ |
| Publicar | ❌ | 🔒 | ✅ |
| Pausar / reactivar | ❌ | 🔒 | ✅ |
| Confirmar disponibilidad | ❌ | 🔒 (con sesión, desde el panel o desde la página enlazada en el email) | ✅ (en nombre del propietario) |
| Marcar como vendida | ❌ | 🔒 | ✅ |
| Archivar | ❌ | 🔒 | ✅ |
| Suspender / levantar suspensión | ❌ | ❌ | ✅ |
| Eliminar definitivamente | ❌ | ❌ (solo borradores propios) | ✅ |
| Reportar | ✅ (rate limit) | ✅ | ✅ |

### Usuarios

| Acción | Usuario | Superadmin |
|---|---|---|
| Editar mi perfil | ✅ | ✅ |
| Listar usuarios | ❌ | ✅ |
| Crear usuario en nombre de otra persona | ❌ | ✅ |
| Ver empresas/publicaciones de otro usuario | ❌ | ✅ |
| Cambiar rol | ❌ | ❌ (solo por comando Artisan) |
| Eliminar cuenta propia | ✅ (starter kit) | ✅ |

### Administración

| Acción | Usuario | Superadmin |
|---|---|---|
| Acceso a `/admin/*` | ❌ | ✅ |
| Ver reportes y resolverlos | ❌ | ✅ |
| Ver publicaciones que necesitan confirmación | 🔒 (las suyas) | ✅ (todas) |
| Ver audit log | ❌ | ✅ |

## Implementación: Policies como única fuente de autorización

- `App\Policies\BusinessPolicy`, `App\Policies\ListingPolicy`, `App\Policies\UserPolicy`, `App\Policies\ListingReportPolicy`.
- Cada Policy implementa `before(User $user, string $ability): ?bool` devolviendo `true` cuando `$user->isSuperadmin()`. Excepción: acciones que ni el superadmin puede hacer (cambiar rol) se comprueban explícitamente y no dependen de `before`.
- Los componentes Livewire y controladores llaman `$this->authorize('update', $listing)` (o `Gate::authorize`) **al inicio de cada acción**, nunca solo al renderizar.
- Las vistas usan `@can` únicamente para mostrar u ocultar controles; nunca como única barrera.
- Los métodos de transición de estado (`publish()`, `pause()`, etc.) viven en `App\Actions\Listings\*` o en el modelo, y son las Policies (no los Actions) quienes deciden **quién** puede; los Actions deciden **si el estado lo permite** (ver [06-listing-lifecycle.md](06-listing-lifecycle.md)).
- El acceso a `/admin` se protege con middleware `EnsureUserIsSuperadmin` además de las Policies.
- Se prohíben comprobaciones sueltas del tipo `if ($user->role === 'superadmin')` dispersas por componentes; se usa `$user->isSuperadmin()` solo dentro de Policies, middleware y el trait de autoría.

## Asistencia por parte del superadmin (caso "llamada de teléfono")

Flujo previsto:

1. El superadmin va a `Admin → Usuarios → Nuevo usuario`, introduce nombre, teléfono y email de la persona. Se crea el usuario con contraseña aleatoria, `email_verified_at` fijado por el admin y `is_assisted = true`; opcionalmente se le envía el email de establecer contraseña. La persona no necesita hacer nada para que su publicación exista.
   - Si la persona **no tiene email**: Fortify exige un email único, así que se usa una dirección de alias del buzón de soporte (`soporte+{slug-nombre}@dominio-avytra`) que entrega en la bandeja del superadmin. Los recordatorios de vigencia llegan ahí y el superadmin confirma en nombre de la persona. La lista "Cuentas asistidas" en admin (`is_assisted`) muestra estas cuentas para seguimiento telefónico. Si más adelante la persona consigue email, el superadmin lo cambia desde admin (audit log).
2. El superadmin va a `Admin → Empresas → Nueva`, selecciona el propietario (buscador de usuarios), rellena los datos e imágenes.
3. Crea la publicación con el mismo wizard que usa el usuario (el wizard acepta un `business` cuyo propietario no es el actor si el actor es superadmin).
4. Publica. El `Listing` queda con `created_by_user_id` = superadmin, `business.owner_user_id` = la persona.
5. Los recordatorios de vigencia llegan al email de contacto del propietario; el superadmin puede confirmar en su nombre desde el panel y queda registrado en `audit_logs` con `acted_on_behalf_of_user_id`.

No hay impersonación. Si más adelante resulta necesaria (por ejemplo, para reproducir un problema visual de un usuario), se documentará como mejora futura ([21-future-roadmap.md](21-future-roadmap.md)).

## Visibilidad pública vs. autorización

La autorización responde "¿puede este usuario hacer X sobre este recurso?". La visibilidad responde "¿qué campos de este recurso se muestran públicamente?". Son mecanismos distintos:

- La autorización se resuelve con Policies.
- La visibilidad se resuelve con campos explícitos (`location_visibility`, `*_disclosure`, `show_*`) y con un **presentador/proyección pública** del listing que solo expone lo permitido. Ver [16-security-and-privacy.md](16-security-and-privacy.md).

## Preguntas resueltas

- **¿Puede un usuario tener varias empresas?** Sí, sin límite en el MVP.
- **¿Puede una empresa cambiar de propietario?** Solo el superadmin (caso: la persona se registra después de que el admin creara la empresa con un usuario provisional).
- **¿Un usuario puede eliminar su cuenta con empresas publicadas?** Sí. Eliminar la cuenta borra sus empresas (con ubicación, perfil online y, desde Phase 3, publicaciones) mediante el Action `DeleteUserAccount`. Las empresas que creó para otros como superadmin no se tocan. Decidido en ADR-017.
