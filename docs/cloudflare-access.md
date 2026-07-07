# Cloudflare Zero Trust — Access con Google como IdP

Resumen de cómo se protegen ciertos subdominios con Cloudflare Access, usando
Google como proveedor de identidad (IdP) en lugar del sistema de OTP por email.

## 1. Configurar el Identity Provider (Google)

En Google Cloud Platform:

1. Entrar en `console.cloud.google.com` y crear un proyecto nuevo.
2. Ir a **APIs & Services → Credentials**.
3. Configurar la pantalla de consentimiento (**Consent Screen**):
   - Audience Type: **External** (para cuentas Gmail personales).
   - Rellenar nombre de la app y email de soporte.
4. Crear un **OAuth Client** de tipo *Web application*.
5. Añadir como Authorized Redirect URI:
   ```
   https://<tu-equipo>.cloudflareaccess.com/cdn-cgi/access/callback
   ```
   (`<tu-equipo>` es el Team Name de la cuenta, visible en Zero Trust → Settings → General).
6. Copiar el **Client ID** y el **Client Secret** generados.

En Cloudflare Zero Trust:

1. Ir a **Zero Trust → Integrations → Identity providers**.
2. **Add new identity provider → Google**.
3. Introducir el Client ID y Client Secret.
4. Guardar y verificar con **Test**.
5. Desactivar **One-time PIN** en Settings → Authentication, dejando solo Google como método de login.

## 2. Políticas de acceso

Las políticas determinan **quién** puede entrar a cada aplicación protegida.
Se usa una política de tipo `Allow` restringida a correos concretos:

**Ruta:** Zero Trust → Access → Applications → *tu app* → Policies

| Action | Rule type | Selector | Valor |
|---|---|---|---|
| Allow | Include | Emails | `tucorreo@ejemplo.com` |
| Allow | Include | Emails ending in | `@tudominio-ejemplo.com` |

**Puntos clave aprendidos:**

- El IdP solo verifica que el usuario tiene una cuenta de Google válida — **no** restringe el acceso por sí solo. La política `Allow` es la que de verdad filtra quién entra.
- Una regla `Include: Everyone` deja pasar cualquier cuenta de Google, aunque el IdP esté bien configurado.
- Cloudflare siempre muestra la pantalla de OTP aunque el correo no esté autorizado — es intencionado, para evitar que se pueda averiguar qué correos son válidos por prueba y error. Con IdP (Google) este problema desaparece.

## 3. Personalización de la pantalla de login

**Ruta:** Zero Trust → Settings → Custom Pages

Se puede añadir un mensaje para usuarios no autorizados, indicando a quién
contactar para solicitar acceso (por ejemplo, un email de contacto en texto
plano, que la mayoría de navegadores detectan automáticamente como enlace).

> La personalización avanzada (HTML, logo, colores propios) requiere plan de pago; en el plan gratuito solo hay texto básico.

## 4. Flujo de autenticación resultante

1. El usuario intenta acceder al subdominio protegido.
2. Cloudflare Access intercepta la petición.
3. Se muestra la pantalla de login con Google como método.
4. El usuario se autentica con su cuenta de Google.
5. Cloudflare comprueba si el email cumple alguna política `Allow`.
6. Si el email está autorizado → acceso concedido. Si no → pantalla de bloqueo de Cloudflare.
