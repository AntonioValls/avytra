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

**Regla de visibilidad:** todo canal relleno en la publicación es público; no hay flags `show_*` porque la única razón de rellenarlo es que se muestre. Si el vendedor no quiere mostrar su teléfono, simplemente no lo rellena. La UI del wizard lo explica con una frase: "Todo lo que escribas aquí se mostrará en tu publicación."

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
2. Canal preferido destacado como botón primario (Lime): "Enviar email", "Llamar", "WhatsApp", "Ir a la web", "Rellenar formulario".
3. Resto de canales como botones secundarios.
4. Notas de contacto.
5. Texto discreto: "Al contactar, menciona que has visto esta publicación en AVYTRA."

### Protección contra scraping y spam

- **Teléfono y WhatsApp** no se renderizan en el HTML inicial. Se muestran tras pulsar "Mostrar teléfono" (acción Livewire con `RateLimiter` por IP: 20 revelaciones/hora). No es infalible pero elimina el scraping trivial y permite medir interés en el futuro.
- **Email**: `mailto:` con asunto prefijado ("Interesado en: {título} — AVYTRA"). Se renderiza también tras clic, por la misma razón. Alternativa evaluada y descartada en MVP: formulario de contacto relay (AVYTRA envía el email por el comprador). Ventajas: oculta el email; desventajas: requiere anti-spam propio, entregabilidad y confianza en que el email llega. Queda en roadmap como "formulario de contacto relay" ligado a estadísticas.
- WhatsApp: enlace `https://wa.me/{E164}?text=` con mensaje prefijado.
- Enlaces externos con `rel="nofollow noopener"` y `target="_blank"`.

## Publicaciones sin canal digital

Caso real: persona mayor que solo tiene teléfono fijo. `preferred_contact_method = phone`, `contact_phone` relleno, sin email. Los **recordatorios de vigencia** requieren un email: se envían al email de la cuenta del propietario (si el superadmin creó la cuenta con su propio email provisional, los recibe él y confirma en nombre de la persona). Ver [13-freshness-and-notifications.md](13-freshness-and-notifications.md).

## Contacto con AVYTRA (soporte)

Datos de contacto de la plataforma en `config/avytra.php` → `support.email`, `support.phone` (env). Se usan en la landing "Publicar", en el pie de página y en el mensaje de publicaciones suspendidas. No es un usuario ni un listing.

## Tests previstos

- Publicar sin método preferido o sin su canal falla con mensaje claro.
- La proyección pública no incluye el email del usuario ni su teléfono de perfil.
- El teléfono no aparece en el HTML inicial de la ficha y sí tras la acción de revelar.
- Rate limit de revelación devuelve respuesta controlada.
- Prefill del wizard no persiste datos hasta completar el paso.
