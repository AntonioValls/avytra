# 12 — Sistema de contacto

## Principio

AVYTRA conecta; no intermedia. En el MVP no hay mensajería interna. El vendedor decide **cómo** y **con qué datos** quiere que le contacten, y esos datos viven en la publicación, no en el perfil de usuario.

## Modelo

Columnas en `listings` (ver [07-database-design.md](07-database-design.md)):

| Campo | Descripción |
|---|---|
| `contact_name` | Nombre que se muestra ("Antonio", "Gestoría López"). No tiene por qué coincidir con el usuario. |
| `preferred_contact_method` | Enum `ContactMethod`: `email`, `phone`, `whatsapp`, `website`, `external_form`, `other`. |
| `contact_email` | Email de contacto público. |
| `contact_phone` | Teléfono (E.164 normalizado; `flux:phone` Pro). |
| `contact_whatsapp` | Número WhatsApp (puede ser el mismo; checkbox "Usar el mismo número"). |
| `contact_website_url` | Web de contacto. |
| `contact_form_url` | Formulario externo (Typeform, web propia…). |
| `contact_other` | Texto libre corto ("Preguntar por Marta en el local"). |
| `contact_notes` | Horario o instrucciones ("Llamar de 9 a 14h"). |

**Regla de visibilidad:** todo canal relleno en la publicación es público, **salvo el email** (desde Phase 11, ADR-019): `contact_email` es el buzón donde llegan los mensajes del formulario relay y nunca se imprime. No hay flags `show_*` porque la única razón de rellenar un canal es que se muestre. Si el vendedor no quiere mostrar su teléfono, simplemente no lo rellena. La UI del wizard lo explica con una frase: "Todo lo que escribas aquí se mostrará en tu publicación, salvo el email."

**Validación al publicar:** `preferred_contact_method` obligatorio y su canal correspondiente relleno (`email` ⇒ `contact_email`, `phone` ⇒ `contact_phone`, `whatsapp` ⇒ `contact_whatsapp`, `website` ⇒ `contact_website_url`, `external_form` ⇒ `contact_form_url`, `other` ⇒ `contact_other`).

## Por qué no se usa el perfil del usuario

- El propietario de la cuenta puede no ser quien atiende los contactos.
- Cuando el superadmin publica en nombre de una persona sin email, el contacto debe ser el teléfono de esa persona.
- Evita exponer el email de login por accidente.
- Permite cambiar el contacto entre publicaciones.

El wizard **sugiere** (prefill visible y editable) nombre y email del usuario en el paso 6 cuando el actor es el propietario. No se guarda nada que el usuario no haya visto en el formulario.

## Presentación pública: "Contactar con el propietario"

Bloque lateral en la ficha:

1. Nombre de contacto.
2. Canal preferido destacado como botón primario (Lime): "Enviar mensaje" (relay), "Llamar", "WhatsApp", "Ir a la web", "Rellenar formulario". "Enviar mensaje" aparece siempre (como secundario si el preferido es otro).
3. Resto de canales como botones secundarios.
4. Notas de contacto.
5. Texto discreto: "Al contactar, menciona que has visto esta publicación en AVYTRA."

### Protección contra scraping y spam

- **Teléfono y WhatsApp** no se renderizan en el HTML inicial. Se muestran tras pulsar "Mostrar teléfono" (acción Livewire con `RateLimiter` por IP: 20 revelaciones/hora). No es infalible pero elimina el scraping trivial y permite medir interés en el futuro.
- **Email**: hasta Phase 10, `mailto:` revelado tras clic. Desde Phase 11 (ADR-019) el email **nunca** se renderiza: el interesado escribe por el formulario relay (ver abajo) y AVYTRA reenvía el mensaje al buzón del vendedor.
- WhatsApp: enlace `https://wa.me/{E164}?text=` con mensaje prefijado.
- Enlaces externos con `rel="nofollow noopener"` y `target="_blank"`.

## Formulario relay "Enviar mensaje" (Phase 11, ADR-019)

- **Dónde:** en toda publicación `published`, dentro del bloque "Contactar con el propietario" (`public.contact-request-form`, modal `contact-request`). No en vendidas ni pausadas (404 si se invoca la acción).
- **Qué pide:** nombre, email, teléfono opcional y mensaje (`avytra.contact.request_message_max_length`, texto plano). Con sesión iniciada, nombre y email se prerrellenan y son editables.
- **Destinatario:** `Listing::contactInboxEmail()` = `contact_email` o, si falta, el email de la cuenta del propietario (mismo criterio que los recordatorios de vigencia). Por eso el botón existe aunque la publicación solo tenga teléfono.
- **Qué pasa:** `SubmitContactRequest` guarda `contact_requests` y envía `ContactRequestReceived` en cola (notificación bajo demanda, `Reply-To` = interesado, botón "Ver mis mensajes"). El vendedor responde desde su cliente de correo; su dirección solo se conoce si contesta. **No se envía copia ni confirmación al remitente**: evitaría que el relay sirva para mandar correo a terceros. Si el email falla tras los reintentos, `failed()` marca `delivery_failed_at` y el mensaje sigue visible en `/panel/mensajes` (docs/09).
- **Anti-spam:** honeypot `website`, tiempo mínimo (`avytra.contact.request_min_seconds_to_submit`), limitador nombrado `contact-request` (`request_rate_limit_per_hour` por IP) y tope diario por publicación e IP (`requests_per_listing_per_day`, sobre `ip_hash`). Los bots reciben el mismo "Mensaje enviado" y se descartan.
- **Presentador:** `PublicListingPresenter::isRelayChannel()` (email) frente a `isSensitiveChannel()` (teléfono, WhatsApp); `contactMethods()` incluye siempre el email; `publicChannel()` y `sensitiveChannel()` devuelven `null` para él.

## Publicaciones sin canal digital

Caso real: persona mayor que solo tiene teléfono fijo. `preferred_contact_method = phone`, `contact_phone` relleno, sin email. Los **recordatorios de vigencia** requieren un email: se envían al email de la cuenta del propietario (si el superadmin creó la cuenta con su propio email provisional, los recibe él y confirma en nombre de la persona). Ver [13-freshness-and-notifications.md](13-freshness-and-notifications.md).

## Contacto con AVYTRA (soporte)

Datos de contacto de la plataforma en `config/avytra.php` → `support.email`, `support.phone` (env). Se usan en la landing "Publicar", en el pie de página y en el mensaje de publicaciones suspendidas. No es un usuario ni un listing.

Implementación (Phase 4): `resources/views/components/public/⚡contact-box.blade.php`. El presentador distingue canales sensibles (`sensitiveChannel()`: teléfono, WhatsApp, email) de públicos (`publicChannel()`: web, formulario, texto libre). El componente lee los valores en cada render y solo tras `reveal()`; nunca los guarda en propiedades públicas (el snapshot de Livewire viaja en el HTML). Limitador `contact-reveal` (`avytra.contact.reveal_rate_limit_per_hour` por IP). Enlaces `mailto:` y `wa.me` con asunto/mensaje prefijado "Interesado en: {título} — AVYTRA".

## Tests previstos

- Publicar sin método preferido o sin su canal falla con mensaje claro.
- La proyección pública no incluye el email del usuario ni su teléfono de perfil.
- El teléfono no aparece en el HTML inicial de la ficha y sí tras la acción de revelar; el email no aparece nunca, ni tras revelar.
- Relay (Phase 11): creación y envío al buzón correcto (`contact_email` o cuenta), `Reply-To`, prefill con sesión, honeypot y tiempo mínimo, limitador por IP y tope por publicación, solo `published`, policy y página de mensajes (solo propios, marcar leído, contador), tarjeta admin y contador de no entregados.
- Rate limit de revelación devuelve respuesta controlada.
- Prefill del wizard no persiste datos hasta completar el paso.
