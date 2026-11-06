# Tablero de solicitudes con flujo de estados

Aplicación para crear, editar y mover solicitudes por un flujo de estados con reglas de negocio,
más un resumen del tablero generado por un LLM (Groq).

| Capa | Tecnología |
|---|---|
| API | PHP 8.4 · Laravel 13 |
| Base de datos | MySQL 8.4 (compatible con MariaDB 10.4 de XAMPP) |
| Frontend | Vue 3 · Vite · Tailwind CSS v4 |
| LLM | Groq, modelo `openai/gpt-oss-120b` |
| Infra | Docker Compose: `db` + `backend` + `frontend` (nginx) |

---

## 1. Instalación

### Docker (recomendada)

```bash
cp .env.example .env          # opcional: poner GROQ_API_KEY
docker compose up -d --build
```

- SPA: http://localhost:8080 · API: http://localhost:8000/api/v1 · MySQL: `localhost:3307` (`root` / `secret`)
- MySQL importa `schema.sql` al crear el volumen. Para empezar de cero: `docker compose down -v && docker compose up -d`.
- nginx sirve la SPA y redirige `/api/*` al backend (mismo origen, sin CORS).
- Variables del `.env`: `GROQ_API_KEY` (vacía → fallback), `GROQ_MODEL`, `LLM_ENABLED`, `DB_ROOT_PASSWORD`,
  `DB_PORT_HOST` / `API_PORT_HOST` / `WEB_PORT_HOST`.

### Local (XAMPP)

Requisitos: PHP ≥ 8.4 con `pdo_mysql`, Composer 2, Node ≥ 20.19, MySQL o MariaDB.

```bash
mysql -u root < schema.sql                 # o: php artisan migrate --seed

cd backend && composer install
cp .env.example .env && php artisan key:generate   # editar DB_* y GROQ_API_KEY
php artisan serve                          # http://localhost:8000

cd frontend && npm install && npm run dev  # http://localhost:5173 (proxy /api → :8000)
```

### Tests

```bash
cd backend && php artisan test   # 44 tests: matriz de transiciones, API, fallback del LLM
```

---

## 2. API

Base `/api/v1`, todo en JSON. Los estados se identifican por su **código string**
(`created`, `in_review`, `in_progress`, `paused`, `done`), nunca por IDs internos.

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/statuses` | Catálogo de estados y sus transiciones |
| GET | `/applications` | Lista solicitudes (`?status=paused` opcional) |
| POST | `/applications` | Crea una solicitud; **siempre entra en `created`** |
| GET | `/applications/{id}` | Detalle |
| PUT/PATCH | `/applications/{id}` | Edita `title`/`description` (no el estado) |
| GET | `/applications/{id}/transitions` | Estado actual, destinos posibles e historial |
| POST | `/applications/{id}/transition` | Ejecuta una transición (`{"target_status": "in_review"}`) |
| GET | `/applications/summary/llm` | Resumen IA (`?refresh=1` ignora la caché). **Siempre 200** |

Cada solicitud incluye `allowed_transitions`, calculado por el backend; el frontend pinta los botones con ese campo.

**Errores de transición:**
- Transición no permitida → **400**, sin cambios en la BD:
  ```json
  { "message": "Transición inválida: no se puede pasar de 'created' a 'done'. …",
    "error": "invalid_transition", "current_status": "created",
    "target_status": "done", "allowed_transitions": ["in_review"] }
  ```
- Estado destino inexistente → **422** (entrada mal formada, no regla de negocio).

**Resumen IA:** `{ "summary": "…", "fallback": false, "cached": false, … }`. Si el LLM falla:
`{ "summary": null, "fallback": true, "message": "El servicio de analítica IA no está disponible…" }`.

---

## 3. Decisiones de arquitectura

**Por qué Laravel:** trae de serie validación declarativa (`FormRequest`, `Rule::enum`), excepciones que se renderizan
como HTTP, cliente HTTP con timeout y fakes para tests, migraciones y API Resources. Los enums nativos de PHP modelan
la máquina de estados sin librerías.

**Máquina de estados**

```
created ──► in_review ──► in_progress ──► done
               │  ▲            │  ▲
               ▼  │            ▼  │
               paused ◄────────┘──┘
```

- `ApplicationStatus` (enum) es la única fuente de verdad del grafo. `paused` solo va y vuelve de `in_review`/`in_progress`.
- `ApplicationTransitionService` es el único código que cambia `status`: transacción + `SELECT … FOR UPDATE`, valida,
  actualiza y registra el historial. Si es inválida lanza `InvalidTransitionException` (→ 400) antes de escribir.
- `status` no es asignable en masa; `POST`/`PUT` lo ignoran.

**Códigos string en vez de IDs:** el código es a la vez valor del enum, PK de `statuses` y FK en `applications.status`.
Es legible, estable entre entornos, no requiere mapeo en el cliente y la FK rechaza códigos inexistentes.

**Capas:** route → Controller delgado → FormRequest → Service → Model → Resource.

**Integración con el LLM**
- **Se envía:** fecha, total, conteo por estado y las 10 solicitudes más recientes (título recortado, estado, fecha).
- **No se envía:** descripciones (texto libre, posibles datos sensibles, tokens innecesarios), IDs ni historial.
- **Cuándo:** al cargar la página (petición separada del listado) y con el botón *Regenerar*; no en cada cambio.
  La UI avisa "Hay cambios desde este resumen".
- **Caché:** 10 min, con el hash del payload como clave. Los fallbacks no se cachean.
- **Resiliencia:** timeout de 4 s. Cualquier fallo, falta de key o `LLM_ENABLED=false` → 200 con `fallback: true`
  y un warning en el log. El listado nunca depende del LLM, y PHP corre con 4 workers.

**Frontend:** una sola página con panel IA, formulario de alta, filtros por estado y tablero kanban responsive.
Edición en modal. Los botones de transición salen de `allowed_transitions`; si el backend responde 400
(p. ej. otra pestaña ya movió la solicitud), se muestra el mensaje y se recarga el tablero.

---

## 4. Base de datos

```
statuses                    applications                     application_status_logs
code       VARCHAR(50) PK ◄─ status      VARCHAR(50) FK       id             BIGINT PK
name       VARCHAR(100)      id          BIGINT PK ◄───────── application_id BIGINT FK (cascade)
sort_order TINYINT           title       VARCHAR(255)         from_status    FK → statuses
is_final   TINYINT(1)        description TEXT NULL            to_status      FK → statuses
                             created_at / updated_at          created_at
```

- InnoDB, `utf8mb4_unicode_ci`. Índice `(status, created_at)` para el filtro y el conteo del resumen.
- Las reglas de transición viven en el dominio (testeadas, 25 combinaciones), no en triggers. La BD garantiza la
  integridad referencial; el historial es append-only.
- **`schema.sql`** es idempotente (MySQL 8 / MariaDB 10.4+): crea la BD `request_board`, las tres tablas, el catálogo
  de estados y 8 solicitudes de prueba que cubren todos los estados con historial coherente. Las migraciones de
  Laravel generan la misma estructura.

---

## 5. Estructura

```
request-board/
├── docker-compose.yml · schema.sql · .env.example
├── backend/   app/{Enums, Exceptions, Services, Http, Models}, database/, routes/api.php, tests/
└── frontend/  src/{App.vue, api.js, statusUi.js, components/}, nginx.conf
```

---

## 6. Uso de IA

- **Claude Code:** asistente de desarrollo para el esqueleto del código, `schema.sql`, Docker, tests y este README.
  Las decisiones de diseño se revisaron y se justifican arriba.
- **Groq (`openai/gpt-oss-120b`):** LLM que usa la app en tiempo de ejecución para el resumen del tablero.
