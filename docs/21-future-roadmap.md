# 21 — Roadmap futuro (NO MVP)

Ideas documentadas para no contaminar el MVP y para comprobar que el dominio actual las admite sin rehacerse. Ninguna se implementa sin convertirse en fase numerada con su propio ADR.

| Idea | Encaje en el dominio actual | Notas |
|---|---|---|
| Favoritos | Tabla pivote `user_listing_favorites` | Requiere registro de compradores; hoy no existe rol comprador, un `user` sirve |
| Alertas y búsquedas guardadas | `saved_searches (user_id, filters json, frequency)` + job diario | Reutiliza los filtros por URL |
| ~~Formulario de contacto relay~~ | `contact_requests` + notificación al propietario | **Hecho en Phase 11 (ADR-019).** Base de estadísticas de contacto |
| Mensajería interna | `conversations`, `messages` | Solo si el relay demuestra demanda |
| Estadísticas del anuncio | `listing_stats_daily (views, contact_reveals, messages)` | Contadores agregados, no tracking individual; `contact_requests` ya cuenta los mensajes |
| NDA / documentos privados / data room | `listing_documents` con acceso por solicitud | Cambia el modelo de confianza; requiere verificación |
| Verificación de empresas | `businesses.verified_at`, `verified_by_user_id` | Badge "Verificada"; proceso manual del superadmin |
| Valoración empresarial orientativa | Servicio de cálculo sobre `listing_financial_metrics` | Con disclaimers; no asesoramiento |
| Asesores / brokers | Rol `advisor` + `business_advisors` pivote | Justificaría paquete de permisos |
| Equipos / colaboración | `teams` (patrón ParkingParaCamiones) | Cambiaría `owner_user_id` → `owner_team_id`; se evaluará solo si hay demanda |
| Ofertas privadas | `offers` sobre `listing` | Requiere compradores registrados |
| IA para mejorar descripciones | Servicio externo opcional en el wizard | Cuidado con privacidad de datos enviados |
| Importación de empresas | Comando + CSV | Para migrar carteras de asesores |
| API pública | Rutas `api/v1` con Resources | Solo lectura de publicaciones públicas |
| App móvil | Consume la API | |
| Internacionalización | Rutas por locale, `lang/*.json`, `*_translations` para contenido de catálogo (patrón ParkingParaCamiones), `currency` | Ya preparado a nivel de datos |
| Páginas SEO por municipio y subcategoría | Rutas + `noindex` si vacías | Cuando haya volumen |
| Impersonación de usuarios | Middleware + banner + audit | Solo si la asistencia directa resulta insuficiente |
| Enlaces de acceso sin contraseña (magic links) | Token de un solo uso + `Auth::login` | Solo si la confirmación con login (ADR-007) genera fricción real; mismo riesgo que un email de reset |
| Moderación previa (`pending_review`, `rejected`) | Estados adicionales entre `draft` y `published` | Si aparece abuso |
| Editor enriquecido (`flux:editor`) | Sanitizador HTML con lista blanca | ADR obligatorio |
| Búsqueda con Scout/Meilisearch | `Searchable` en `Listing` | Cuando LIKE/FULLTEXT no baste |
| Radio geográfico | Bounding box + Haversine sobre `public_*` | Nunca sobre coordenadas privadas |
| Multi-sede | UI sobre `locations.is_primary` | Modelo ya lo admite |
| Monetización (destacados, planes, servicios a asesores) | Tablas nuevas relacionadas por `listing_id`/`user_id`; Cashier si procede | El dominio no lo impide; la ordenación pública deberá etiquetar los destacados como publicidad (lección de ParkingParaCamiones) |
| Exportación de datos RGPD | Comando + job | |
| Tracking de rebotes de email | Webhooks del proveedor | Mejora la fiabilidad de recordatorios |
| Turnstile/CAPTCHA | En registro y reportes | Solo si hay spam |
| CSP estricta | `nonce` para Livewire/Alpine | Phase 10 evalúa; roadmap si es costoso |
