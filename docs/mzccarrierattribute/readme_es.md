# MZC Carrier Attribute — Documentación

## Versión 1.0.0

## Autor: Marcin Zbijowski Consulting

## Compatibilidad: PrestaShop 8.0.0 – 9.x | PHP 8.1+

---

## Tabla de contenidos

1. Descripción general
2. Requisitos
3. Instalación
4. Cómo funciona
5. Configuración
6. Ejemplos
7. Comportamiento del carrito
8. Actualizaciones de transportistas
9. Configuración Multi-Tienda
10. Rendimiento
11. Traducciones
12. Solución de problemas
13. Desinstalación
14. Soporte

---

## 1. Descripción general

MZC Carrier Attribute te permite restringir qué transportistas están disponibles en el pago según los valores de atributos del producto (ej. tallas, materiales, pesos).

Utiliza un modelo de lista blanca: para cada valor de atributo, seleccionas qué transportistas están permitidos. Los valores de atributos sin transportistas seleccionados no imponen ninguna restricción — todos los transportistas permanecen disponibles. Solo los valores de atributos con transportistas asignados explícitamente limitan las opciones de envío.

Cuando un cliente añade productos con valores de atributos restringidos al carrito, el módulo elimina automáticamente los transportistas no permitidos de las opciones de entrega en el pago. Si hay múltiples valores de atributos restringidos en el carrito, solo estarán disponibles los transportistas permitidos por TODOS ellos.

Los productos simples sin combinaciones nunca se ven afectados por este módulo.

---

## 2. Requisitos

- PrestaShop 8.0.0 o posterior (compatible hasta 9.x)
- PHP 8.1 o posterior
- Al menos un transportista activo configurado
- Al menos un grupo de atributos con valores (ej. Talla: S, M, L, XL)

---

## 3. Instalación

### Desde PrestaShop Addons

1. Descarga el archivo ZIP del módulo desde tu cuenta de Addons
2. Ve al back-office de tu PrestaShop
3. Navega a Módulos > Gestor de módulos
4. Haz clic en Subir un módulo
5. Selecciona el archivo ZIP y espera a que se complete la instalación
6. Haz clic en Configurar para configurar el módulo

### Instalación manual

1. Extrae el archivo ZIP
2. Sube la carpeta mzccarrierattribute al directorio modules de tu PrestaShop vía FTP
3. Ve a Módulos > Gestor de módulos en el back-office
4. Busca MZC Carrier Attribute
5. Haz clic en Instalar, luego en Configurar

Después de la instalación, un enlace al módulo también aparece en el menú izquierdo del back-office bajo **Transporte** > **Atributos transportistas**, proporcionando acceso rápido a la página de configuración.

---

## 4. Cómo funciona

### El modelo de lista blanca

El módulo opera con un principio simple de lista blanca:

1. Cada valor de atributo del producto (ej. «XXL» en el grupo «Talla») puede tener asignados uno o más transportistas permitidos
2. Si un valor de atributo no tiene transportistas asignados, no impone ninguna restricción — todos los transportistas permanecen disponibles
3. Si un valor de atributo tiene transportistas asignados, solo esos transportistas están permitidos cuando un producto con ese atributo está en el carrito
4. Cuando múltiples valores de atributos restringidos están en el carrito, el módulo calcula la intersección — solo los transportistas que aparecen en TODAS las listas blancas están disponibles

### Ejemplo de lógica

Consideremos estas reglas:

- Talla «XXL» → transportistas permitidos: Transportista A, Transportista B
- Talla «S» → sin transportistas asignados (sin restricción)
- Material «Vidrio» → transportistas permitidos: Transportista B, Transportista C

Escenarios:

- El carrito contiene un producto en talla S → todos los transportistas disponibles (sin restricción)
- El carrito contiene un producto en talla XXL → solo Transportista A y B disponibles
- El carrito contiene un producto en talla XXL y material Vidrio → solo Transportista B disponible (intersección de {A, B} y {B, C})
- El carrito contiene un producto en talla XXL y otro en talla S → solo Transportista A y B disponibles (solo XXL impone restricciones)

### Hooks utilizados

El módulo utiliza tres hooks de PrestaShop:

- **actionFilterDeliveryOptionList** — el hook de filtrado principal. Elimina los transportistas no permitidos de las opciones de entrega presentadas en el pago.
- **actionCarrierUpdate** — invalida la caché interna cuando un transportista se modifica en el back-office, asegurando que las reglas siempre hagan referencia a los datos actuales del transportista.
- **actionPresentCart** — añade una anotación a la etiqueta del subtotal de envío en el resumen del carrito cuando hay restricciones activas, para que el cliente vea un aviso de que algunos métodos de envío están restringidos por atributos del producto.

---

## 5. Configuración

Navega a Módulos > Gestor de módulos, encuentra MZC Carrier Attribute y haz clic en Configurar. Alternativamente, usa el acceso directo **Transporte** > **Atributos transportistas** en el menú de la izquierda.

### Panel de reglas

La página de configuración muestra todos los grupos de atributos y sus valores en paneles desplegables. Para cada valor de atributo, una fila de casillas de verificación lista todos los transportistas activos. Marca los transportistas que quieres permitir para ese valor de atributo.

### Filtrado

Usa el botón Mostrar solo atributos configurados para filtrar la vista a solo aquellos valores de atributos que tienen al menos un transportista asignado. Esto es útil cuando tienes muchos atributos pero solo unas pocas reglas.

Haz clic en Mostrar todos los atributos para volver a la vista completa.

### Guardado

Haz clic en Guardar reglas en la parte inferior de la página para guardar todas las reglas a la vez. El módulo guarda todas las reglas en una sola transacción para mantener la consistencia.

Un mensaje de éxito confirma el guardado. Si no se encuentran atributos, se muestra un mensaje de error.

---

## 6. Ejemplos

### Caso de uso 1: Artículos sobredimensionados

Vendes muebles y algunos artículos solo se envían por transportistas de carga.

1. Crea un grupo de atributos «Clase de envío» con valores como «Estándar» y «Solo carga»
2. Asigna «Solo carga» → tus transportistas de carga
3. Deja «Estándar» sin transportistas seleccionados (todos permitidos)
4. Aplica «Solo carga» a las combinaciones de productos sobredimensionados

Resultado: Los productos sobredimensionados solo muestran transportistas de carga en el pago.

### Caso de uso 2: Restricciones regionales

Vendes productos que solo pueden ser enviados por ciertos transportistas regionales debido a restricciones de materiales.

1. Usa un atributo existente como «Material» con valores como «Batería de litio», «Inflamable», «Estándar»
2. Asigna «Batería de litio» → transportistas certificados para materiales peligrosos
3. Asigna «Inflamable» → transportistas certificados para mercancías inflamables
4. Deja «Estándar» sin transportistas seleccionados

Resultado: Los productos con materiales restringidos solo muestran transportistas certificados.

### Caso de uso 3: Envío basado en talla

Vendes ropa y las tallas XXL requieren un transportista específico con embalaje más grande.

1. En el grupo de atributos «Talla», asigna «XXL» y «XXXL» → tu transportista de paquetes grandes
2. Deja todas las demás tallas sin transportistas seleccionados

Resultado: Los pedidos que contienen artículos XXL o XXXL solo muestran el transportista de paquetes grandes.

---

## 7. Comportamiento del carrito

### Múltiples productos

Cuando el carrito contiene múltiples productos con atributos restringidos, el módulo calcula la intersección de todos los conjuntos de transportistas permitidos. Esto significa:

- Si el Producto A permite transportistas {1, 2, 3} y el Producto B permite {2, 3, 4}, solo los transportistas {2, 3} están disponibles
- Si la intersección está vacía (ningún transportista es permitido por todos los productos restringidos), todas las opciones de entrega se eliminan y el cliente no puede proceder al pago

### Aviso en el subtotal de envío

Cuando las restricciones de transportistas están activas para el carrito actual, el módulo añade el texto «(algunos métodos restringidos por atributos del producto)» a la etiqueta del subtotal de envío en el resumen del carrito. Esto proporciona transparencia al cliente sobre por qué algunos métodos de envío pueden no estar disponibles.

### Carrito mixto

Si el carrito contiene tanto productos restringidos como no restringidos, solo se aplican las reglas de los productos restringidos. Los productos no restringidos (productos simples o aquellos con atributos no configurados) no limitan la disponibilidad de transportistas.

---

## 8. Actualizaciones de transportistas

Cuando modificas un transportista en el back-office de PrestaShop (renombrar, cambiar zonas, actualizar precios), el módulo invalida automáticamente su caché interna en la siguiente carga de página. Esto asegura que las reglas siempre hagan referencia a los datos actuales del transportista.

PrestaShop crea un nuevo registro de transportista con un nuevo ID cuando editas un transportista, pero preserva el ID de referencia del transportista. El módulo usa IDs de referencia, por lo que las reglas siguen siendo válidas incluso después de editar transportistas.

Si eliminas un transportista por completo, sus reglas se vuelven inactivas pero no se eliminan automáticamente de la configuración. El módulo maneja correctamente las referencias a transportistas eliminados omitiéndolos durante el filtrado.

---

## 9. Configuración Multi-Tienda

El módulo soporta la funcionalidad multi-tienda de PrestaShop. Cada tienda puede tener sus propias reglas transportista-atributo configuradas independientemente.

Las reglas se almacenan por tienda en la tabla Configuration, por lo que al cambiar entre contextos de tienda en el back-office se muestran y guardan las reglas para la tienda seleccionada.

---

## 10. Rendimiento

### Estrategia de caché

El módulo utiliza una estrategia de caché de dos niveles:

1. **Caché de configuración** — las reglas se serializan como JSON en la tabla ps_configuration para lecturas rápidas. Esto evita consultar la tabla de reglas en cada carga de página.
2. **Caché en memoria por petición** — una vez cargadas, las reglas y las correspondencias de referencias de transportistas se almacenan en caché en propiedades estáticas durante la duración de la petición.

La caché se invalida automáticamente cuando:

- Las reglas se guardan a través de la página de configuración
- Un transportista se actualiza en el back-office

### Base de datos

El módulo crea una sola tabla (mzc_carrier_attr_rule) con columnas indexadas para búsquedas eficientes. La tabla usa una clave única compuesta para evitar reglas duplicadas.

---

## 11. Traducciones

El módulo incluye traducciones completas para 5 idiomas:

- Inglés (en)
- Polaco (pl)
- Francés (fr)
- Español (es)
- Italiano (it)

Cada archivo de traducción cubre las 20 cadenas traducibles en la clase del módulo, la plantilla del formulario de reglas y las plantillas de advertencia.

### Añadir o editar traducciones

Para traducir el módulo a idiomas adicionales o modificar las traducciones existentes:

1. Ve a Internacional > Traducciones en tu back-office
2. Selecciona Traducciones de módulos instalados en el menú desplegable Tipo
3. Selecciona el idioma de destino
4. Encuentra MZC Carrier Attribute en la lista de módulos
5. Introduce tus traducciones y haz clic en Guardar

PrestaShop guarda el archivo de traducción automáticamente en modules/mzccarrierattribute/translations/.

---

## 12. Solución de problemas

### Las restricciones de transportistas no se aplican

- Verifica que las reglas estén guardadas: ve a la configuración del módulo y comprueba que los transportistas correctos estén marcados para los valores de atributos relevantes
- Asegúrate de que el transportista está activo y no eliminado
- Limpia la caché de PrestaShop (Parámetros avanzados > Rendimiento > Limpiar caché)
- Verifica que el producto tenga una combinación con el valor de atributo restringido (los productos simples no se ven afectados)

### Todos los transportistas desaparecen en el pago

- Esto ocurre cuando la intersección de todos los valores de atributos restringidos resulta en un conjunto vacío (ningún transportista es permitido por todos los productos restringidos en el carrito)
- Revisa tus reglas para asegurar que al menos un transportista es común a todos los valores de atributos restringidos que podrían estar simultáneamente en el carrito
- Usa el filtro «Mostrar solo atributos configurados» para revisar todas las reglas activas

### Las reglas no se muestran en la página de configuración

- Asegúrate de tener al menos un grupo de atributos con valores
- Asegúrate de tener al menos un transportista activo
- Si falta alguno, el módulo muestra una advertencia en lugar del formulario de reglas

### Página de configuración lenta

- Si tienes cientos de valores de atributos, la página de configuración puede cargarse lentamente debido al formulario grande
- Usa el filtro «Mostrar solo atributos configurados» para reducir los datos mostrados
- Considera crear un grupo de atributos dedicado «Clase de envío» con menos valores en lugar de configurar reglas para cada atributo

---

## 13. Desinstalación

1. Ve a Módulos > Gestor de módulos
2. Encuentra MZC Carrier Attribute
3. Haz clic en la flecha desplegable y selecciona Desinstalar
4. Confirma la desinstalación

El módulo:

- Eliminará la tabla de reglas (mzc_carrier_attr_rule)
- Eliminará el valor de configuración (MZC_CARRIER_ATTR_RULES)
- Desregistrará todos los hooks

Todas las reglas transportista-atributo serán eliminadas permanentemente.

---

## 14. Soporte

Para soporte, reportes de errores o solicitudes de funciones, contáctanos a través del sistema de mensajería de PrestaShop Addons en la página del producto del módulo.

Al reportar un problema, por favor incluye:

- Versión de PrestaShop
- Versión de PHP
- Nombre y versión del tema
- Lista de transportistas activos y grupos de atributos
- Pasos para reproducir el problema
