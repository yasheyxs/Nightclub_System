# Nightclub Management System – Backend API

Sistema backend diseñado para la gestión integral de un nightclub, orientado a la operación diaria, control de ventas y generación de reportes. Está pensado para entornos reales de uso nocturno, con foco en estabilidad, automatización y consistencia de datos.

---

## Visión general del sistema

La API organiza sus funcionalidades en módulos bien definidos:

* Dashboard y reportes
* Eventos
* Entradas (tipos de ticket)
* Venta de entradas en puerta
* Anticipadas (preventas)
* Usuarios y roles
* Autenticación

El sistema está preparado para trabajar en producción con impresoras físicas, exportaciones y lógica automática que reduce la intervención manual del operador.

---

## Funcionalidades principales

### 1. Dashboard y reportes

* Cálculo de métricas mensuales:

  * Cantidad de eventos
  * Entradas vendidas
  * Recaudación total
  * Ocupación promedio
* Exportación a CSV con estructura jerárquica (mes → eventos).
* Generación de reportes en PDF:

  * Resumen de métricas
  * Evento en curso
  * Listado completo de eventos
* Sanitizado manual de acentos y generación propia del PDF sin dependencias externas pesadas.

<img width="1826" height="801" alt="image" src="https://github.com/user-attachments/assets/0ce1bd20-71ff-4470-aaec-67bff646b082" />

---

### 2. Gestión de eventos

* Desactivación automática de eventos pasados.
* Generación automática de eventos para los próximos sábados si no existen:

  * Nombre por fecha
  * Cupo predefinido
* Múltiples modos de consulta:

  * Próximos sábados
  * Calendario de 60 días
  * Listado general
* CRUD completo para crear, editar y desactivar eventos.

Esta lógica garantiza continuidad operativa sin depender de cargas manuales constantes.

<img width="1531" height="1488" alt="image" src="https://github.com/user-attachments/assets/5dd54162-8e35-44fe-bcf5-44532f3f8754" />

---

### 3. Entradas (tipos de ticket)

* CRUD completo de tipos de entrada.
* Soporte para cambios automáticos de precio por rango horario:

  * Activación/desactivación del cambio automático
  * Hora de inicio y fin
  * Precio alternativo
* Permite manejar estrategias reales de pricing según horario.

  <img width="1467" height="579" alt="image" src="https://github.com/user-attachments/assets/b88676c0-0504-4628-aef4-7034bf04926a" />

---

### 4. Venta de entradas en puerta

* Devuelve catálogo de:

  * Eventos activos
  * Tipos de entrada disponibles
  * Ventas acumuladas por evento y tipo
* Registro de ventas con validaciones:

  * Evento activo
  * Precio base correcto
  * Normalización del flag “incluye trago”
* Impresión de tickets físicos:

  * Texto centrado
  * Mensajes de cortesía
  * Comandos ESC/POS para corte de papel
  * Envío directo a impresora vía comando de Windows

Pensado para operación rápida en horarios pico.

<img width="1505" height="800" alt="image" src="https://github.com/user-attachments/assets/84b78595-375d-4ba5-b506-25889ad3f384" />

<img width="556" height="588" alt="image" src="https://github.com/user-attachments/assets/4dfd1cb2-528c-47d6-a556-bf9a431d9894" />

---

### 5. Anticipadas (preventas)

* Listado de preventas con información completa de evento y entrada.
* Soporte para impresión física de tickets con la misma lógica que ventas en puerta.

<img width="1468" height="655" alt="image" src="https://github.com/user-attachments/assets/3d538df9-4711-4e46-b885-2d44aaa0a32a" />

---

### 6. Usuarios y roles

* CRUD de usuarios con validaciones de seguridad:

  * Contraseña mínima de 8 caracteres
  * Cambio de contraseña con verificación de clave actual
* Protección operativa:

  * No permite eliminar el último administrador activo
  * Requiere contraseña del administrador para eliminar usuarios

<img width="1442" height="446" alt="image" src="https://github.com/user-attachments/assets/a54d3c40-ef43-45fa-86ff-933319d24bfe" />


---

### 7. Autenticación

* Login mediante teléfono y contraseña.
* Validaciones:

  * Usuario activo
  * Normalización de roles
* Generación de token de sesión aleatorio, pensado para simplicidad y control en entornos cerrados.

  <img width="617" height="474" alt="image" src="https://github.com/user-attachments/assets/24e464ef-c07d-4cba-ae5b-defd40d167bd" />


---

## Detalles técnicos y decisiones destacables

* Auto-generación de eventos futuros para evitar huecos operativos.
* Integración directa con impresoras físicas desde backend.
* Preventa unificada con ventas para métricas consistentes.
* Bloqueos de seguridad a nivel lógico, no solo de UI.
* Exportables del dashboard en CSV y PDF generados manualmente.
* Arquitectura simple, clara y mantenible, orientada a negocios reales.

---

## Estado del proyecto

El sistema se encuentra funcional y orientado a uso productivo en un entorno de nightclub real. Está diseñado para ser extendido fácilmente (frontend web, panel administrativo, integraciones externas).
