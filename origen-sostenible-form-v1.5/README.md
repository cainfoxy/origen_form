# Origen Sostenible - Formulario de Evaluación Energética v1.5

Plugin de WordPress para formulario de evaluación energética con cálculo de presupuesto para instalaciones solares fotovoltaicas.

## Requisitos

- WordPress 5.0+
- PHP 7.4+

## Instalación

1. Comprimir la carpeta `origen-sostenible-form-v1.5/` en un archivo ZIP
2. En WordPress ir a **Plugins > Añadir nuevo > Subir plugin**
3. Seleccionar el archivo ZIP y pulsar **Instalar ahora**
4. Activar el plugin

## Uso

### Insertar el formulario

Usar el shortcode en cualquier página o entrada:

```
[origen_form]
```

### Panel de administración

Al activar el plugin aparece un menú **Formularios Origen** con tres secciones:

1. **Ver formularios**: Lista paginada de todos los envíos con filtros por fecha y tipo de solicitud. Click en "Ver detalles" para ver toda la información de cada registro.

2. **Exportar CSV**: Descarga un archivo CSV con todos los registros. El archivo usa separador punto y coma (;) y codificación UTF-8 con BOM para compatibilidad con Excel.

3. **Precios**: Formulario para editar los precios de instalaciones solares, baterías y cargador de vehículo eléctrico. Los cambios se aplican inmediatamente a nuevos formularios.

## Estructura del formulario

El formulario tiene 15 pasos:

- **Pasos 1-6**: Obligatorios para todos (tipo propiedad, consumo, servicios, plazo, ubicación, datos contacto)
- **Paso 7**: Pregunta decisiva - ¿quiere presupuesto inmediato?
  - Si **No**: envía el formulario y termina
  - Si **Sí**: continúa a los pasos 8-15
- **Pasos 8-14**: Datos técnicos para el presupuesto (techo, orientación, superficie, financiación, etc.)
- **Paso 15**: Muestra el presupuesto calculado y permite enviar la solicitud

## Emails

El plugin envía automáticamente 3 emails al recibir un formulario:

1. **Al usuario**: Confirmación con resumen de datos y presupuesto (si lo solicitó)
2. **Al administrador**: Email con todos los datos y enlace al panel
3. **A comercial@origensostenible.net**: Misma copia que el admin

## Archivos del plugin

```
origen-sostenible-form-v1.5/
├── origen-sostenible-form.php   # Archivo principal
├── budget-calculator.php        # Clase de cálculos
├── css/
│   ├── origen-form.css          # Estilos frontend
│   └── origen-admin.css         # Estilos panel admin
├── js/
│   └── origen-form.js           # JavaScript frontend
├── templates/
│   ├── admin-page.php           # Listado de formularios
│   ├── export-page.php          # Exportar CSV
│   └── pricing-settings.php     # Configuración de precios
└── README.md
```

## Soporte

- Email: info@origensostenible.net
- Teléfono: 607 44 55 41
- Web: www.origensostenible.net
