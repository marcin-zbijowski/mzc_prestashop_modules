# MZC Landing Newsletter — Documentación

## Versión 1.0.0

## Autor: Marcin Zbijowski Consulting

## Compatibilidad: PrestaShop 8.0.0 – 9.x | PHP 8.1+

---

## Tabla de contenidos

1. Descripción general
2. Requisitos
3. Instalación
4. Configuración
5. Preajustes CSS
6. Referencia de clases CSS
7. Cumplimiento del RGPD
8. Google Tag Manager y analítica
9. Configuración Multi-Tienda
10. Gestión de suscriptores
11. Configuración SEO
12. Interacción con el modo mantenimiento
13. Omisión para administradores
14. Limitación de frecuencia
15. Características de seguridad
16. Traducciones
17. Solución de problemas
18. Desinstalación
19. Soporte

---

## 1. Descripción general

MZC Landing Newsletter añade un modo de Página de destino a tu tienda PrestaShop. Cuando está activado, todos los visitantes del front-office ven una página con la marca de tu tienda, incluyendo el logo, un mensaje personalizado y un formulario de suscripción al boletín. Los administradores y las direcciones IP en lista blanca omiten la página de destino y acceden normalmente a la tienda.

Esta funcionalidad es independiente del modo de mantenimiento integrado de PrestaShop. Úsala cuando tu tienda no esté lista todavía, durante una migración, configuración del catálogo, cambio de marca, o en cualquier momento que desees recopilar direcciones de correo electrónico antes del lanzamiento.

Los suscriptores se almacenan en la tabla nativa de boletín de PrestaShop (ps_emailsubscription), por lo que aparecen automáticamente en tus herramientas de boletín existentes sin necesidad de sincronización ni exportación.

---

## 2. Requisitos

- PrestaShop 8.0.0 o posterior (compatible hasta 9.x)
- PHP 8.1 o posterior
- Módulo ps_emailsubscription instalado (incluido por defecto con PrestaShop)
- Módulo psgdpr instalado y configurado (opcional, para la casilla de consentimiento RGPD)

---

## 3. Instalación

### Desde PrestaShop Addons

1. Descarga el archivo ZIP del módulo desde tu cuenta de Addons
2. Accede al back-office de tu PrestaShop
3. Navega a Módulos > Gestor de módulos
4. Haz clic en Subir un módulo
5. Selecciona el archivo ZIP y espera a que se complete la instalación
6. Haz clic en Configurar para configurar el módulo

### Instalación manual

1. Extrae el archivo ZIP
2. Sube la carpeta mzc_landing_newsletter al directorio modules de tu PrestaShop vía FTP
3. Accede a Módulos > Gestor de módulos en el back-office
4. Busca MZC Landing Newsletter
5. Haz clic en Instalar, luego en Configurar

---

## 4. Configuración

Navega a Módulos > Gestor de módulos, encuentra MZC Landing Newsletter y haz clic en Configurar.

### Activar el modo página de destino

Cambia la opción Activar página de destino a Sí para activar la página para todos los visitantes. Cambia a No para desactivar y mostrar tu tienda normal.

### Mensaje de la página de destino

Introduce el mensaje que se mostrará en la página de destino. Este campo soporta:

- Edición de texto enriquecido (negrita, cursiva, enlaces, formato)
- Contenido multilingüe — usa el selector de idioma para introducir diferentes mensajes por idioma
- Contenido HTML — para formato avanzado

Mensaje por defecto: ¡Próximamente! Nuestra tienda está en construcción. Suscríbete a nuestro boletín para recibir una notificación cuando lancemos.

### CSS personalizado

Introduce reglas CSS personalizadas para cambiar la apariencia de la página de destino. Déjalo vacío para usar el estilo por defecto. Consulta la Sección 6 para la lista completa de clases CSS disponibles.

---

## 5. Preajustes CSS

Tres preajustes integrados están disponibles en el panel de Preajustes CSS en la página de configuración. Haz clic en Cargar preajuste para rellenar el campo CSS personalizado con los estilos del preajuste.

### Modern Dark (Moderno oscuro)

Fondo en degradado púrpura con efecto glassmorphism en la tarjeta. Botón de suscripción en degradado violeta-azul, campos de entrada translúcidos y logo invertido para fondos oscuros. Ideal para marcas tecnológicas, gaming o estilo de vida moderno.

### Modern Light (Moderno claro)

Fondo en degradado cálido de melocotón a blanco con una tarjeta grande redondeada y sombras profundas. Botón de suscripción en degradado naranja con tipografía elegante y espaciado de letras. Ideal para marcas de moda, belleza o estilo de vida.

### Soft Gray (Gris suave)

Fondo plano gris claro con una tarjeta con borde sutil. Tonos grises atenuados con un botón de suscripción oscuro discreto. Minimalista y elegante. Ideal para marcas profesionales, B2B o minimalistas.

Cada preajuste puede usarse tal cual o modificarse en el campo CSS personalizado después de cargarlo.

Importante: Cargar un preajuste reemplaza cualquier CSS personalizado existente. Si tienes estilos personalizados, cópialos antes de cargar un preajuste.

---

## 6. Referencia de clases CSS

Las siguientes clases CSS están disponibles para personalización. Una tabla de referencia completa con descripciones se muestra en el panel Referencia de clases CSS en la página de configuración.

### Clases de diseño

- .mzc-landing-container — wrapper exterior, cubre todo el viewport, controla el color de fondo o degradado
- .mzc-landing-content — la tarjeta centrada o caja de contenido, controla max-width, padding, fondo, border-radius y sombra

### Logo

- .mzc-landing-logo — div wrapper del logo de la tienda
- .mzc-landing-logo img — la imagen del logo, controla la altura y anchura máximas

### Mensaje

- .mzc-landing-message — div wrapper para el título y el texto del párrafo
- .mzc-landing-message h1 — el título principal
- .mzc-landing-message h2 — estilo de título alternativo
- .mzc-landing-message h3 — estilo de título alternativo
- .mzc-landing-message p — texto del párrafo bajo el título

### Formulario

- .mzc-landing-form-wrapper — wrapper para toda el área del formulario
- .mzc-form-group — el contenedor de la fila campo-botón
- .mzc-form-input — el campo de entrada de e-mail
- .mzc-form-button — el botón de suscripción

### Feedback y RGPD

- .mzc-form-feedback — el área de mensaje de éxito o error bajo el formulario
- .mzc-gdpr-consent — wrapper para la casilla de consentimiento RGPD y la etiqueta

### Ejemplo

```css
.mzc-landing-container {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.mzc-landing-content {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.mzc-form-button {
  background: #764ba2;
  color: #ffffff;
}

.mzc-form-button:hover {
  background: #667eea;
}
```

---

## 7. Cumplimiento del RGPD

El módulo se integra con el módulo oficial RGPD de PrestaShop (psgdpr) a través de tres hooks.

### Casilla de consentimiento

Cuando psgdpr está instalado y configurado, una casilla de consentimiento con tu mensaje configurado aparece debajo del campo de e-mail en la página de destino. El botón de suscripción está desactivado hasta que el visitante marca la casilla. Esto es gestionado automáticamente por el JavaScript del módulo psgdpr.

Para configurar el mensaje de consentimiento, accede a Módulos > Gestor de módulos > Cumplimiento RGPD oficial > Configurar, y configura el mensaje de consentimiento para MZC Landing Newsletter.

### Eliminación de datos

Cuando se procesa una solicitud de eliminación de datos RGPD, el módulo elimina cualquier dirección de correo electrónico coincidente de la tabla de suscripción al boletín y limpia los registros de limitación de frecuencia relacionados.

### Exportación de datos

Cuando se procesa una solicitud de exportación de datos RGPD, el módulo devuelve todos los registros de suscripción al boletín que coincidan con la dirección de correo solicitada, incluyendo la fecha de suscripción y la dirección IP de registro.

---

## 8. Google Tag Manager y analítica

La página de destino renderiza tres hooks estándar de PrestaShop que usan los módulos de seguimiento:

- displayHeader — renderizado dentro de la sección head del HTML. Usado por Google Tag Manager, Google Analytics, Facebook Pixel y módulos similares para inyectar sus scripts de seguimiento e inicialización del dataLayer.
- displayAfterBodyOpeningTag — renderizado inmediatamente después de la etiqueta body de apertura. Usado por Google Tag Manager para su iframe noscript de respaldo.
- displayBeforeBodyClosingTag — renderizado antes de la etiqueta body de cierre. Usado por algunos módulos de seguimiento para la carga diferida de scripts.

Esto significa que cualquier módulo de seguimiento que use el sistema de hooks estándar de PrestaShop funcionará en la página de destino sin configuración adicional. Compatibilidad verificada con:

- Google Tag Manager (gtmmodule)
- PrestaShop Google Analytics (ps_googleanalytics)
- PrestaShop Marketing with Google (psxmarketingwithgoogle)

---

## 9. Configuración Multi-Tienda

El módulo soporta completamente la funcionalidad multi-tienda de PrestaShop.

### Configuración por tienda

Cuando multi-tienda está activo y seleccionas una tienda específica en el selector de contexto:

- Cada campo de configuración muestra una casilla de anulación
- Marca la casilla para establecer un valor específico de tienda que anula el valor global por defecto
- Desmarca la casilla para heredar el valor de la configuración Todas las tiendas

### Escenarios comunes

- Activar el modo landing para una nueva tienda manteniendo las demás activas: Establece MZC_LANDING_ENABLED en No a nivel de Todas las tiendas, luego anula a Sí para la tienda específica
- Usar diferentes mensajes por tienda: Establece un mensaje por defecto en Todas las tiendas, luego anula con mensajes específicos donde sea necesario
- Usar diferente CSS por tienda: Cada tienda puede tener su propio estilo visual anulando el campo CSS personalizado

---

## 10. Gestión de suscriptores

### Lista de suscriptores

El panel Suscriptores en la página de configuración muestra todas las direcciones de correo electrónico recopiladas a través de la página de destino, identificadas por la etiqueta de origen mzc_landing_page. La lista muestra:

- Dirección de correo electrónico
- Idioma en el momento de la suscripción
- Dirección IP de registro
- Fecha de suscripción

La lista está paginada a 20 entradas por página. Usa los enlaces de navegación en la parte inferior para navegar.

Haz clic en Actualizar lista para recargar los datos de suscriptores.

### Exportación CSV

Haz clic en Exportar CSV para descargar todos los suscriptores de la página de destino como un archivo de valores separados por comas. La exportación incluye todos los suscriptores (no solo la página actual), con columnas: e-mail, idioma, IP y fecha.

### Integración con ps_emailsubscription

Dado que el módulo usa la tabla nativa de boletín de PrestaShop, los suscriptores recopilados en la página de destino también aparecen en:

- La lista de suscriptores del módulo ps_emailsubscription
- Cualquier herramienta de exportación de boletín que lea de la tabla emailsubscription
- Las integraciones de Mailchimp, Sendinblue y otras conectadas a PrestaShop

---

## 11. Configuración SEO

La página de destino carga automáticamente los metadatos SEO desde la configuración de tu tienda para la página index (página de inicio):

- Meta title — usado como título de la página HTML
- Meta description — renderizado como etiqueta meta description
- Meta keywords — renderizado como etiqueta meta keywords (si está configurado)

Para configurar estos valores, accede a Parámetros de la tienda > Tráfico y SEO > SEO y URL, encuentra la página etiquetada como index y edita el meta title, meta description y meta keywords.

Si no se ha configurado meta title, el módulo usa el nombre de la tienda por defecto.

La página de destino envía un código de estado HTTP 503 (Service Unavailable) con un encabezado Retry-After. Esto indica a los motores de búsqueda que el sitio está temporalmente no disponible y que deben volver más tarde, preservando tus clasificaciones existentes.

---

## 12. Interacción con el modo mantenimiento

Importante: Desactiva el modo mantenimiento integrado de PrestaShop cuando uses el modo página de destino.

El modo mantenimiento de PrestaShop (Parámetros de la tienda > General > Mantenimiento) y el modo página de destino de este módulo son funcionalidades independientes. Si ambos están activados simultáneamente, el modo mantenimiento de PrestaShop tiene prioridad porque se ejecuta antes en el ciclo de vida de la solicitud, antes de que se ejecute el hook de este módulo.

Para acceder a los ajustes de mantenimiento, ve a Parámetros de la tienda > General > Mantenimiento en tu back-office y establece Activar tienda en Sí.

Flujo de trabajo recomendado:

1. Desactiva el modo mantenimiento de PrestaShop (establece Activar tienda en Sí)
2. Activa el modo página de destino de MZC Landing Newsletter
3. Trabaja en tu tienda — puedes acceder a ella a través de tu dirección IP en lista blanca
4. Cuando estés listo para lanzar, desactiva el modo página de destino
5. Tu tienda está inmediatamente disponible para todos los visitantes

---

## 13. Omisión para administradores

Cuando el modo página de destino está activado, los siguientes usuarios pueden acceder a la tienda completa:

### Lista blanca de IP

Cualquier dirección IP incluida en Parámetros de la tienda > General > Mantenimiento > IP de mantenimiento omite la página de destino. Añade tu dirección IP allí para trabajar en tu tienda mientras los visitantes ven la página de destino. Se pueden separar múltiples direcciones IP con comas. Se soporta la notación CIDR (ej. 192.168.1.0/24).

### Administradores conectados

Si el ajuste PS_MAINTENANCE_ALLOW_ADMINS está activado, cualquier usuario con una sesión activa de back-office omite automáticamente la página de destino. El módulo lee la cookie de administrador de PrestaShop para detectar administradores conectados.

---

## 14. Limitación de frecuencia

Para prevenir el spam y los abusos, el endpoint de suscripción impone un límite de 3 envíos por dirección IP por ventana de 10 minutos.

Cuando se supera el límite, el visitante ve un mensaje pidiéndole que lo intente de nuevo más tarde. El contador de límite se reinicia automáticamente después de 10 minutos.

Los datos de limitación de frecuencia (direcciones IP y marcas de tiempo) se almacenan en una tabla de base de datos dedicada y se limpian automáticamente. Las entradas expiradas de la IP actual se purgan en cada solicitud, con una limpieza global probabilística del 1% para prevenir el crecimiento de la tabla.

---

## 15. Características de seguridad

### Protección CSRF

El formulario de suscripción incluye un token CSRF de rotación temporal que cambia cada hora. Se aceptan tanto los tokens de la hora actual como los de la hora anterior durante la validación para evitar rechazos en los límites horarios.

### Protección XSS

El CSS personalizado introducido en el back-office se sanea antes de guardar. Las etiquetas HTML se eliminan y las secuencias de ruptura de etiqueta style se neutralizan para prevenir la inyección de scripts.

### Content Security Policy

La página de destino envía un encabezado Content-Security-Policy que restringe las fuentes de scripts a self e inline (requerido para módulos de seguimiento), y permite estilos desde self, inline y fuentes HTTPS (requerido para fuentes web).

### Validación de e-mail

Las direcciones de correo electrónico se validan usando el método integrado Validate::isEmail() de PrestaShop antes de cualquier operación en base de datos.

---

## 16. Traducciones

El módulo incluye traducciones completas para 5 idiomas:

- Inglés (en)
- Polaco (pl)
- Francés (fr)
- Español (es)
- Italiano (it)

Cada archivo de traducción cubre las 87 cadenas traducibles en la clase del módulo, el controlador de suscripción y la plantilla de la página de destino.

### Añadir o editar traducciones

Para traducir el módulo a idiomas adicionales o modificar las traducciones existentes:

1. Accede a Internacional > Traducciones en tu back-office
2. Selecciona Traducciones de módulos instalados en el desplegable Tipo
3. Selecciona el idioma destino
4. Encuentra MZC Landing Newsletter en la lista de módulos
5. Introduce tus traducciones y haz clic en Guardar

PrestaShop guarda el archivo de traducción automáticamente en modules/mzc_landing_newsletter/translations/.

---

## 17. Solución de problemas

### La página de destino no se muestra

- Verifica que MZC_LANDING_ENABLED está establecido en Sí en la configuración del módulo
- Comprueba que el modo mantenimiento de PrestaShop está desactivado (Parámetros de la tienda > General > Mantenimiento > Activar tienda = Sí)
- Verifica que tu IP no está en la lista blanca de IP de mantenimiento
- Limpia la caché de PrestaShop (Parámetros avanzados > Rendimiento > Limpiar caché)

### La casilla RGPD no aparece

- Verifica que el módulo psgdpr está instalado y activado
- Accede a la configuración de psgdpr y asegúrate de que un mensaje de consentimiento está configurado para MZC Landing Newsletter
- Limpia la caché de PrestaShop y recarga la página de destino

### Las fuentes no se cargan correctamente

- Esto generalmente ocurre cuando las fuentes del tema (ej. Google Fonts) se cargan a través del hook displayHeader. El módulo renderiza este hook, por lo que las fuentes deberían cargarse. Si no, limpia la caché de tu navegador con Ctrl+Shift+R (Cmd+Shift+R en Mac)
- Comprueba la consola de herramientas de desarrollo del navegador para errores de Content Security Policy

### Los scripts de seguimiento no se activan

- Verifica que tu módulo de seguimiento usa hooks estándar de PrestaShop (displayHeader, displayAfterBodyOpeningTag o displayBeforeBodyClosingTag)
- Comprueba la consola de herramientas de desarrollo del navegador para errores de JavaScript
- Algunos módulos de seguimiento pueden requerir un contexto de página específico que no está disponible en la página de destino

### El botón de suscripción no funciona

- Comprueba la consola de herramientas de desarrollo del navegador para errores de JavaScript
- Verifica que la casilla de consentimiento psgdpr está marcada (si RGPD está activado)
- Comprueba si la limitación de frecuencia se ha activado (máx. 3 por IP por 10 minutos)

### Los suscriptores no aparecen en la lista

- Haz clic en Actualizar lista en la página de configuración
- Verifica que estás viendo el contexto de tienda correcto en configuraciones multi-tienda
- Comprueba la tabla de base de datos ps_emailsubscription para entradas con http_referer = mzc_landing_page

---

## 18. Desinstalación

1. Accede a Módulos > Gestor de módulos
2. Encuentra MZC Landing Newsletter
3. Haz clic en la flecha desplegable y selecciona Desinstalar
4. Confirma la desinstalación

El módulo:

- Eliminará todos los valores de configuración (MZC_LANDING_ENABLED, MZC_LANDING_MESSAGE, MZC_LANDING_CSS)
- Eliminará la tabla de limitación de frecuencia (mzc_landing_ratelimit)
- Desregistrará todos los hooks

Los suscriptores del boletín en la tabla emailsubscription NO se eliminan durante la desinstalación, ya que se comparten con el módulo ps_emailsubscription.

---

## 19. Soporte

Para soporte, informes de errores o solicitudes de funcionalidades, contáctanos a través del sistema de mensajería de PrestaShop Addons en la página del producto del módulo.

Al informar de un problema, por favor incluye:

- Versión de PrestaShop
- Versión de PHP
- Nombre y versión del tema
- Lista de otros módulos instalados
- Salida de la consola de herramientas de desarrollo del navegador (si aplica)
- Pasos para reproducir el problema
