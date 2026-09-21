# 02 — Alcance del MVP

## Definición

El MVP es la versión mínima con la que AVYTRA puede lanzarse públicamente, recibir publicaciones reales y mantenerlas vigentes sin intervención manual constante. Se construye por fases (ver [20-development-phases.md](20-development-phases.md)); el MVP equivale a completar las fases 1 a 10.

## Dentro del MVP

### Público (sin registro)

- Home con propuesta de valor, buscador y acceso a explorar.
- Explorar empresas con filtros esenciales: texto, categoría, tipo de negocio (físico/online/híbrido), tipo de operación, provincia, rango de precio. Ordenación por recientes y precio.
- Tarjeta de empresa.
- Ficha pública de publicación: galería, descripción, características, precio, información económica pública, datos operativos, mapa (según visibilidad), información online (si procede), contacto, "Disponibilidad confirmada hace N días".
- Páginas de categoría con contenido real.
- Reportar publicación (con y sin registro, con rate limiting).
- Páginas legales mínimas (aviso legal, privacidad, cookies) como vistas estáticas.
- SEO: slugs, title/description, canonical, Open Graph, Schema.org, sitemap, robots, redirecciones por cambio de slug, tratamiento de vendidas/pausadas.

### Usuario registrado

- Registro, login, reset, verificación de email, 2FA opcional, passkeys (ya incluidos en el starter kit).
- Perfil y ajustes (ya incluidos).
- Mis empresas: crear, editar, listar. Varias empresas por usuario.
- Mis publicaciones: crear mediante wizard, guardar borrador, continuar después, publicar, pausar, reactivar, marcar como vendida, confirmar disponibilidad, editar.
- Logo, portada y galería por empresa.
- Configuración de contacto por publicación y de visibilidad de ubicación y cifras.
- Recepción de recordatorios de vigencia por email con enlace de confirmación de un clic.

### Superadministrador

- Listado y búsqueda de usuarios, empresas y publicaciones.
- Crear y editar empresas y publicaciones para cualquier usuario (asignando propietario).
- Crear un usuario en nombre de una persona (sin contraseña conocida; la persona la establece vía reset).
- Publicar, pausar, archivar, marcar como vendida, suspender, reactivar, confirmar vigencia en nombre del propietario.
- Ver publicaciones que necesitan confirmación o pausadas automáticamente.
- Revisar y resolver reportes.
- Trazabilidad de acciones administrativas.

### Sistema

- Scheduler con recordatorios (día 45 y 55) y pausa automática (día 60), valores configurables.
- Cola de trabajos (`database` en local; `redis` o `database` en producción) para emails y conversiones de imagen.
- Tests Pest de reglas de negocio, policies, estados, visibilidad y rutas públicas.

## Fuera del MVP (documentado en 21-future-roadmap.md)

- Favoritos, alertas, búsquedas guardadas.
- Mensajería interna, NDA, documentos privados, data room.
- Estadísticas de anuncio (visitas, contactos).
- Valoración empresarial, empresas verificadas, asesores/brokers, equipos, colaboración.
- Ofertas privadas, IA para descripciones, importación, API, app móvil.
- Internacionalización (multi-idioma, multi-moneda). La arquitectura queda preparada.
- Monetización de cualquier tipo.
- Impersonación de usuarios.
- Moderación previa a la publicación (`pending_review`/`rejected`). El MVP usa moderación posterior con suspensión.
- Búsqueda con motor externo (Scout, Meilisearch, etc.).
- Páginas SEO geográficas por municipio (solo provincia si tiene contenido).
- Editor de texto enriquecido para descripciones (el MVP usa texto plano con párrafos).
- Múltiples ubicaciones por empresa (el modelo lo permite; la UI del MVP gestiona una).

## Decisiones de recorte del MVP (por qué)

| Recorte | Motivo |
|---|---|
| Sin moderación previa | Plataforma gratuita con un solo administrador; bloquear publicaciones hasta revisión frenaría el crecimiento inicial. La suspensión posterior y los reportes cubren el riesgo. |
| Filtros limitados a seis | Cada filtro adicional necesita datos rellenados por los vendedores; facturación y antigüedad son opcionales y filtrarlas produciría resultados vacíos. |
| Sin mensajería | El contacto directo por email/teléfono/WhatsApp es lo que este público ya usa. Un buzón interno añadiría complejidad sin demanda probada. |
| Sin métricas | El vendedor necesita saber si su publicación está viva y qué debe hacer, no gráficos. |
| Texto plano en descripciones | Evita sanitización de HTML y XSS. `flux:editor` queda como mejora futura. |
