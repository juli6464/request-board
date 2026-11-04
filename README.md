# Tablero de solicitudes con flujo de estados

Aplicación para crear, editar y mover solicitudes por un flujo de estados con reglas de negocio,
más un resumen del tablero generado por un LLM (Groq).

| Capa | Tecnología |
|---|---|
| API | PHP 8.4 · Laravel 13 |
| Base de datos | MySQL 8.4 (compatible con MariaDB 10.4 de XAMPP) |
| Frontend | Vue 3 (SFC, Composition API) · Vite · Tailwind CSS v4 |
| LLM | Groq (API compatible con OpenAI), modelo `openai/gpt-oss-120b` |
| Infra | Docker Compose: `db` + `backend` + `frontend` (nginx) |

---

## 1. Instalación y ejecución

### Opción A — Docker Compose (recomendada)

Requisitos: Docker Desktop.

```bash
cp .env.example .env          # variables de compose (opcional: GROQ_API_KEY)
docker compose up -d --build
```

| Servicio | URL |
|---|---|
| SPA | http://localhost:8080 |
| API directa | http://localhost:8000/api/v1 (configurable con `API_PORT_HOST`) |
| MySQL | `localhost:3307`, usuario `root`, contraseña `secret` |

- La primera vez que se crea el volumen, MySQL **importa `schema.sql` automáticamente** (estructura + datos de prueba).
- Para empezar de cero: `docker compose down -v && docker compose up -d`.
- La SPA y la API comparten origen: nginx sirve el build de Vue y redirige `/api/*` al backend, así que no hace falta CORS.

**Variables del `.env` de la raíz (compose):**

| Variable | Por defecto | Uso |
|---|---|---|
| `GROQ_API_KEY` | vacío | API key de Groq. Vacía → el resumen responde con fallback. |
| `GROQ_MODEL` | `openai/gpt-oss-120b` | Modelo de Groq. |
| `LLM_ENABLED` | `true` | `false` apaga el LLM sin quitar la key. |
| `DB_ROOT_PASSWORD` | `secret` | Contraseña de root en MySQL. |
| `DB_PORT_HOST` / `API_PORT_HOST` / `WEB_PORT_HOST` | `3307` / `8000` / `8080` | Puertos publicados en el host. |

### Opción B — Local (XAMPP / PHP instalado)

Requisitos: PHP ≥ 8.4 con `pdo_mysql`, Composer 2, Node ≥ 20.19, MySQL o MariaDB.

```bash
# 1) Base de datos
mysql -u root < schema.sql                # o phpMyAdmin > Importar > schema.sql

# 2) API
cd backend
composer install
cp .env.example .env && php artisan key:generate
# editar .env: DB_*, y GROQ_API_KEY si se quiere el resumen IA
php artisan serve                          # http://localhost:8000

# 3) Frontend (otra terminal)
cd frontend
npm install
npm run dev                                # http://localhost:5173  (proxy /api -> :8000)
```

> Alternativa a `schema.sql`: `php artisan migrate --seed` crea las mismas tablas y datos mediante migraciones.

**Variables relevantes de `backend/.env`:** `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE=request_board`,
`DB_USERNAME`, `DB_PASSWORD`, `LLM_ENABLED`, `GROQ_API_KEY`, `GROQ_MODEL`, `GROQ_API_URL`, `PHP_CLI_SERVER_WORKERS=4`.

### Tests

```bash
cd backend && php artisan test         # 44 tests: matriz completa de transiciones, API, fallback del LLM
# sin PHP local:
docker run --rm -v "$PWD/backend:/app" -w /app composer:2 php artisan test
```

---

## 2. Documentación de la API

Base: `/api/v1`. Todo es JSON. **Los estados se identifican siempre por su código string**
(`created`, `in_review`, `in_progress`, `paused`, `done`), nunca por IDs internos.

### Recurso `Application`

```json
{
  "id": 9,
  "title": "Migración del CRM",
  "description": "…",
  "status": "in_review",
  "status_label": "En revisión",
  "allowed_transitions": ["in_progress", "paused"],
  "created_at": "2026-11-04T13:28:34+00:00",
  "updated_at": "2026-11-04T13:28:35+00:00"
}
```

`allowed_transitions` lo calcula el backend. El frontend pinta los botones a partir de este campo,
así la regla de negocio vive en un solo lugar.

### Endpoints

| Método | Ruta | Descripción | Respuestas |
|---|---|---|---|
| GET | `/statuses` | Catálogo de estados con sus transiciones permitidas | 200 |
| GET | `/applications` | Lista las solicitudes. Filtro opcional `?status=paused` | 200, 422 (estado desconocido) |
| POST | `/applications` | Crea una solicitud. **Siempre entra en `created`** (se ignora cualquier `status` enviado) | 201, 422 |
| GET | `/applications/{id}` | Detalle de una solicitud | 200, 404 |
| PUT/PATCH | `/applications/{id}` | Edita `title` y/o `description` (el estado no se puede cambiar aquí) | 200, 404, 422 |
| GET | `/applications/{id}/transitions` | **Consulta** transiciones: estado actual, destinos posibles e historial | 200, 404 |
| POST | `/applications/{id}/transition` | **Ejecuta** una transición | 200, 400, 404, 422 |
| GET | `/applications/summary/llm` | Resumen IA del tablero. `?refresh=1` ignora la caché | **siempre 200** |

#### Ejemplos

```bash
# Crear
curl -X POST localhost:8080/api/v1/applications -H 'Content-Type: application/json' \
     -d '{"title":"Alta proveedor","description":"Validar RUT"}'

# Transición válida
curl -X POST localhost:8080/api/v1/applications/9/transition -H 'Content-Type: application/json' \
     -d '{"target_status":"in_review"}'
```

**Transición inválida → `400 Bad Request`** (la BD no cambia):

```json
{
  "message": "Transición inválida: no se puede pasar de 'created' a 'done'. Desde ese estado solo se permite pasar a: 'in_review'.",
  "error": "invalid_transition",
  "current_status": "created",
  "target_status": "done",
  "allowed_transitions": ["in_review"]
}
```

**Estado destino inexistente → `422`** (error de validación: no es una regla de negocio, es una entrada mal formada).

**Consultar transiciones** `GET /applications/9/transitions`:

```json
{ "data": {
    "current_status": "in_review",
    "allowed_transitions": [{"code":"in_progress","name":"En progreso"},{"code":"paused","name":"Pausada"}],
    "history": [{"from_status":"created","to_status":"in_review","created_at":"2026-11-04T13:28:35+00:00"}]
} }
```

**Resumen IA** `GET /applications/summary/llm`:

```json
// Éxito
{ "summary": "Hay 9 solicitudes…", "fallback": false, "message": null,
  "generated_at": "2026-11-04T13:30:00+00:00", "cached": false }

// LLM caído, timeout, sin API key o deshabilitado (HTTP 200)
{ "summary": null, "fallback": true,
  "message": "El servicio de analítica IA no está disponible en este momento." }
```

---

## 3. Decisiones de arquitectura

### Por qué PHP / Laravel
La prueba permitía Python o PHP. Elegí Laravel porque trae de serie lo que la prueba evalúa:
validación declarativa (`FormRequest`, `Rule::enum`), excepciones que se convierten en respuestas HTTP
(`render()`), un cliente HTTP con `timeout` y fakes para tests, migraciones y API Resources para controlar
exactamente qué se expone. Además, los enums nativos de PHP 8 (backed enums) modelan la máquina de estados sin librerías.

### Máquina de estados
- **`App\Enums\ApplicationStatus`** (backed enum string) es la única fuente de verdad del grafo:

  ```
  created ──► in_review ──► in_progress ──► done
                 │  ▲            │  ▲
                 ▼  │            ▼  │
                 paused ◄────────┘──┘
  ```
  `allowedTransitions()` devuelve los destinos de cada estado. `paused` solo es alcanzable desde
  `in_review`/`in_progress` y solo vuelve a ellos, así que nunca llega directo a `done`.
- **`App\Services\ApplicationTransitionService`** es el único código que cambia `status`. Abre una transacción,
  bloquea la fila (`SELECT … FOR UPDATE`) para que dos peticiones simultáneas no validen contra el mismo estado viejo,
  valida, actualiza y escribe el historial. Si la transición no es válida, lanza `InvalidTransitionException`
  **antes de escribir nada**.
- **`App\Exceptions\InvalidTransitionException`** es una excepción de dominio que se convierte en `400` con un mensaje
  descriptivo y los destinos válidos. El controlador no tiene `try/catch`.
- `status` **no es asignable en masa** en el modelo. `POST`/`PUT` lo ignoran, y una solicitud nueva siempre nace en `created`.

### Identificación de estados sin IDs internos
Cada estado se identifica por un **código string estable** (`in_review`), que es a la vez el valor del enum,
la PK del catálogo `statuses` y el valor guardado en `applications.status` (FK).
- Es legible en la API, en logs y en SQL, y no cambia entre entornos, a diferencia de un autoincremental.
- El cliente no tiene que mantener un mapeo de IDs.
- La FK hace que la base de datos rechace códigos inexistentes aunque alguien escriba saltándose la API.

### Capas
`routes/api.php` → **Controller** delgado → **FormRequest** (validación) → **Service** (reglas) → **Model** (Eloquent) → **Resource** (forma de la respuesta).

### Integración con el LLM
- **Qué se envía** (`LlmSummaryService::buildPayload`): fecha de hoy, total, **conteo por estado** y las **10 solicitudes
  más recientes con título (recortado a 120 caracteres), estado y fecha de creación**.
- **Qué NO se envía y por qué**: las **descripciones** (texto libre, largo, que puede tener datos sensibles y gasta tokens sin
  aportar a un resumen agregado), los **IDs**, `updated_at` y el historial. El conteo por estado ya es un dato agregado:
  el modelo no necesita las filas para saber dónde se acumula el trabajo.
- **Cuándo se genera**: al cargar la página, en una petición **separada** del listado, y bajo demanda con el botón
  *Regenerar*. No se dispara con cada cambio, para no pagar una llamada por clic. En cambio, la UI marca
  "Hay cambios desde este resumen".
- **Caché**: el resultado se guarda 10 minutos con una clave que es el hash del payload. Si los datos no cambiaron, no se vuelve
  a llamar al LLM. Si cambiaron, la clave es distinta y se regenera. Los fallbacks no se guardan en caché.
- **Resiliencia**: `Http::timeout(4)` + `connectTimeout(2)`. Timeout, error HTTP, respuesta vacía, falta de API key o
  `LLM_ENABLED=false` → **HTTP 200** con `{summary:null, fallback:true, message}` y un warning en el log. El listado usa
  otro endpoint, así que nunca depende del LLM. Además, el servidor PHP corre con 4 workers para que una llamada lenta al LLM
  no bloquee las demás peticiones.

### Frontend
- Una sola página: el panel IA arriba, el formulario de alta, chips de filtro por estado con contadores y un tablero tipo kanban
  (1 columna en móvil → 2 → 3 → 5 en pantallas anchas). Al filtrar se muestra solo esa columna en una cuadrícula.
- Las solicitudes nuevas aparecen al instante (se insertan con la respuesta del `POST`, sin recargar la lista).
- La edición se hace en un modal (Esc o clic fuera para cerrar; en móvil aparece como hoja inferior).
- Los botones de transición se generan desde `allowed_transitions`. El frontend solo conoce los verbos y colores
  ("Pausar", "Reanudar", "Volver a revisión"…). Si el backend responde 400 (por ejemplo, otra pestaña ya movió la solicitud),
  se muestra el mensaje y se vuelve a cargar el tablero.

---

## 4. El script `schema.sql`

Es un script idempotente para importar en MySQL 8 o MariaDB 10.4+:
1. `CREATE DATABASE IF NOT EXISTS request_board` (utf8mb4) y `USE`.
2. `DROP TABLE IF EXISTS` de las tres tablas (con `FOREIGN_KEY_CHECKS=0`), así se puede reimportar.
3. **`statuses`** + sus 5 filas del catálogo. Este catálogo es parte del esquema, no un dato de prueba.
4. **`applications`** con la FK `status → statuses.code` (`ON UPDATE CASCADE`, `ON DELETE RESTRICT`) y el índice `(status, created_at)`.
5. **`application_status_logs`** (historial) con FKs a la solicitud (`ON DELETE CASCADE`) y a los estados.
6. **Datos de prueba**: 8 solicitudes que cubren todos los estados (2 `created`, 1 `in_review`, 2 `in_progress`,
   2 `paused`, 1 `done`) y un historial coherente con cada estado actual. Por ejemplo, la #8 pasó por una pausa antes de terminar.

En Docker se monta en `/docker-entrypoint-initdb.d/`, así que lo ejecuta MySQL al crear el volumen. Las migraciones de Laravel
(`backend/database/migrations`) generan exactamente la misma estructura (se usan en los tests con SQLite en memoria).

---

## 5. Información de la base de datos

```
statuses                         applications                         application_status_logs
─────────────────────            ──────────────────────────           ───────────────────────────
code        VARCHAR(50) PK  ◄─┬─ status      VARCHAR(50) FK           id             BIGINT PK
name        VARCHAR(100)      │  id          BIGINT PK AI  ◄───────── application_id BIGINT FK (cascade)
sort_order  TINYINT           │  title       VARCHAR(255)             from_status    VARCHAR(50) FK ─┐
is_final    TINYINT(1)        │  description TEXT NULL                to_status      VARCHAR(50) FK ─┤
                              │  created_at  TIMESTAMP                created_at     TIMESTAMP       │
                              │  updated_at  TIMESTAMP                                               │
                              └──────────────────────────────────────────────────────────────────────┘
```

- **Motor InnoDB** (transacciones y bloqueo por fila para las transiciones). Charset `utf8mb4_unicode_ci` (tildes y emojis).
- **Las reglas de transición no están en triggers**: viven en el dominio, donde se pueden testear (la matriz completa de 25
  combinaciones está en `tests/Unit/ApplicationStatusTest.php`). La BD garantiza la integridad referencial y la aplicación garantiza el flujo.
- **Índice `(status, created_at)`**: cubre el filtro por estado y el `GROUP BY status` del resumen IA.
- **El historial es append-only**: sirve para auditar y para el endpoint "consultar transiciones".
- La base de datos se llama `request_board`. En Docker se expone en el puerto **3307** para no chocar con el MySQL de XAMPP (3306).

---

## 6. Estructura del proyecto

```
request-board/
├── docker-compose.yml          # db (MySQL + schema.sql) · backend · frontend
├── schema.sql                  # Esquema + datos de prueba
├── .env.example                # Variables de compose
├── backend/                    # Laravel 13
│   ├── app/
│   │   ├── Enums/ApplicationStatus.php            # Estados + grafo de transiciones
│   │   ├── Exceptions/InvalidTransitionException.php  # → HTTP 400
│   │   ├── Services/
│   │   │   ├── ApplicationTransitionService.php   # Única vía para cambiar el estado
│   │   │   └── LlmSummaryService.php              # Payload mínimo, timeout, caché, fallback
│   │   ├── Http/
│   │   │   ├── Controllers/  (Application, ApplicationTransition, LlmSummary, Status)
│   │   │   ├── Requests/     (Store, Update, Transition)
│   │   │   └── Resources/ApplicationResource.php
│   │   └── Models/ (Application, ApplicationStatusLog, Status)
│   ├── database/ (migrations, seeders, factories)
│   ├── routes/api.php
│   ├── tests/ (Unit/ApplicationStatusTest, Feature/ApplicationApiTest, Feature/LlmSummaryTest)
│   └── Dockerfile
└── frontend/                   # Vue 3 + Vite + Tailwind
    ├── src/
    │   ├── App.vue             # Estado de la página, tablero y filtros
    │   ├── api.js              # Cliente fetch + manejo de errores
    │   ├── statusUi.js         # Colores y verbos por estado (solo presentación)
    │   └── components/ (SummaryPanel, CreateForm, ApplicationCard, EditModal)
    ├── nginx.conf              # Sirve la SPA y hace proxy de /api
    └── Dockerfile
```

---

## 7. Uso de IA

- **Claude Code (Anthropic)**: lo usé como asistente de desarrollo para generar el esqueleto del código (enum, servicios,
  controladores, tests, componentes de Vue), `schema.sql`, la configuración de Docker y este README, a partir del enunciado
  de la prueba. También corrió los tests y el smoke test con `docker compose`. Las decisiones de diseño (códigos string como
  identificadores, datos mínimos para el LLM, caché por huella del payload, bloqueo de fila) se revisaron y se justifican arriba.
- **Groq (`openai/gpt-oss-120b`)**: es el LLM que usa la aplicación en tiempo de ejecución para generar el resumen del tablero.
