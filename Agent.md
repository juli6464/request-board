Eres un Desarrollador Senior Full-Stack especializado en PHP (Laravel 10+), Vue.js 3 y MySQL. Tu objetivo es construir la prueba técnica Tablero de solicitudes con flujo de estados".

### 1. REGLAS DE NEGOCIO Y MÁQUINA DE ESTADOS (PHP 8.2 + Laravel)
Define un Enum PHP para los estados y una clase de servicio dedicada a las transiciones:
- Estados válidos (Enum `ApplicationStatus`): `created`, `in_review`, `in_progress`, `paused`, `done`.
- Flujo principal: `created` -> `in_review` -> `in_progress` -> `done`.
- Reglas especiales de Pausa:
  - Solo se puede pasar a `paused` desde `in_review` o `in_progress`.
  - Desde `paused`, solo se puede volver a `in_review` o `in_progress`.
- Si un intento de cambio de estado viola las reglas, la aplicación debe lanzar una excepción de dominio (`InvalidTransitionException`) que retorne una respuesta HTTP 400 Bad Request con un mensaje descriptivo sin modificar la BD.
- En las peticiones HTTP y respuestas de la API, los estados se identifican EXCLUSIVAMENTE por su código string (`created`, `in_review`, etc.), NUNCA por IDs internos.

### 2. BASE DE DATOS (MySQL)
Crea la migración y el script `schema.sql`:
- Tabla `applications`: `id` (BIGINT AUTO_INCREMENT PRIMARY KEY), `title` (VARCHAR 255), `description` (TEXT), `status` (VARCHAR 50), `created_at` (TIMESTAMP), `updated_at` (TIMESTAMP).
- Incluye un archivo `schema.sql` listo para importar con estructuras de tablas y datos semilla de prueba (seeds).

### 3. API REST (Laravel Controllers & Resources)
Endpoints a construir:
- `GET /api/v1/applications`: Listar todas las solicitudes.
- `POST /api/v1/applications`: Crear solicitud (entra automáticamente en estado `created`).
- `GET /api/v1/applications/{id}`: Detalle de solicitud.
- `PUT /api/v1/applications/{id}`: Actualización de título y descripción.
- `POST /api/v1/applications/{id}/transition`: Ejecutar transición de estado enviando `{"target_status": "in_review"}`.
- `GET /api/v1/applications/summary/llm`: Obtener el resumen de IA generado por el LLM.

### 4. INTEGRACIÓN LLM & RESILIENCIA
- Crea un servicio `LlmSummaryService` que envíe solo los datos necesarios al LLM (conteo por estado, títulos recientes y fechas de creación), evitando enviar descripciones extensas.
- Utiliza `Http::timeout(4)` de Laravel para realizar la petición al proveedor LLM (Groq).
- Si la petición al LLM falla, expira por timeout o la API Key no existe/está deshabilitada, la API debe responder con HTTP 200 y una estructura de degradación elegante:
  `{"summary": null, "fallback": true, "message": "El servicio de analítica IA no está disponible en este momento."}`.

### 5. FRONTEND (Vue.js 3 + Tailwind CSS)
Construye una SPA fluida y responsive en la que se pueda:
- Crear solicitudes en tiempo real.
- Ver el tablero/listado de solicitudes clasificadas o filtradas por estado.
- Editar el título y descripción (edición inline o modal).
- Ejecutar transiciones mediante botones dinámicos habilitados únicamente según el estado actual de la solicitud.
- Mostrar una sección superior prominente con el resumen generado por el LLM, incluyendo spinner de carga y mensaje de error/fallback.

### 6. ENTREGABLES DE DOCUMENTACIÓN (README.md)
Genera un `README.md` detallado que contenga:
1. Pasos de instalación, configuración del `.env` y ejecución con Docker Compose.
2. Documentación clara de la API REST.
3. Decisiones de arquitectura. 
4. Explicación del script `schema.sql`.
5. Uso de IA: qué herramientas usaste. 
6. Información relevante de la base de datos. 