# 🎯 Teamboard

**Teamboard** es una aplicación web completa de gestión de proyectos estilo Kanban, diseñada para equipos colaborativos que necesitan organizar tareas en tiempo real. Integra Inteligencia Artificial mediante **Harvis**, un asistente inteligente que puede generar, organizar y priorizar tareas automáticamente.

---

## 🚀 Características Principales

### ✅ Gestión de Proyectos Kanban
- **Tableros Colaborativos**: Crea múltiples proyectos con columnas personalizables (To Do, In Progress, Done, etc.)
- **Drag & Drop**: Arrastra y suelta tarjetas entre columnas y reorganiza columnas completas de forma visual
- **Tarjetas Completas**: Cada tarjeta incluye título, descripción, prioridad, fecha de vencimiento, etiquetas y asignación de usuarios
- **Sistema de Etiquetas**: Organiza tarjetas con etiquetas de colores personalizables
- **Niveles de Prioridad**: Clasifica tareas como `low`, `normal`, `high` o `urgent`

### 🤖 Asistente IA - Harvis
- **Generación Automática de Tareas**: Describe lo que necesitas y Harvis creará las tarjetas con título y descripción detallada
- **Reordenamiento Inteligente**: Pídele a Harvis que priorice u ordene tus tareas y lo hará automáticamente
- **Contexto por Columna o Global**: Puede trabajar sobre una columna específica o analizar todo el proyecto
- **Integración con Gemini API**: Utiliza modelos avanzados de Google (Gemini 2.5 Flash) para comprender el contexto del proyecto

### ⚡ Colaboración en Tiempo Real
- **WebSockets con Laravel Reverb**: Todos los cambios se propagan instantáneamente a todos los usuarios conectados
- **Eventos Broadcast**: Creación, edición, movimiento y eliminación de tarjetas en tiempo real
- **Sincronización de Columnas**: Reorganización de columnas visible al instante para todo el equipo
- **Sidebar Actualizado**: Los tableros nuevos aparecen automáticamente en la barra lateral de todos los usuarios

### 👥 Gestión de Usuarios y Equipos
- **Sistema de Autenticación**: Registro e inicio de sesión seguro con Laravel Sanctum
- **Roles**: Propietarios de proyectos y miembros colaboradores
- **Asignación de Tareas**: Asigna tarjetas a miembros específicos del equipo
- **Notificaciones**: Sistema de notificaciones cuando un usuario es asignado a una tarjeta
- **Perfiles Personalizables**: Actualiza nombre, email y avatar de usuario

### 💬 Sistema de Comentarios
- **Comunicación Contextual**: Agrega comentarios directamente en cada tarjeta
- **Ordenados Cronológicamente**: Visualiza la conversación en orden del más antiguo al más reciente
- **Metadata de Usuario**: Cada comentario muestra quién lo escribió

---

## 🛠️ Stack Tecnológico

### Backend
| Tecnología | Versión | Propósito |
|------------|---------|-----------|
| **[Laravel](https://laravel.com)** | 11.x | Framework PHP principal |
| **PHP** | 8.2+ | Lenguaje del servidor |
| **MySQL** | 8.4 | Base de datos relacional |
| **[Laravel Reverb](https://reverb.laravel.com)** | 1.0+ | WebSockets en tiempo real |
| **[Laravel Sanctum](https://laravel.com/docs/sanctum)** | 4.0+ | Autenticación de API |
| **[OpenAI PHP](https://github.com/openai-php/laravel)** | 0.18+ | Cliente para Gemini API |
| **Docker (Sail)** | - | Contenedorización y desarrollo |

### Frontend
| Tecnología | Versión | Propósito |
|------------|---------|-----------|
| **[Vue.js](https://vuejs.org)** | 3.5+ | Framework de interfaz (Composition API) |
| **[Vite](https://vitejs.dev)** | 5.0+ | Build tool y dev server |
| **[Pinia](https://pinia.vuejs.org)** | 2.1+ | Gestión de estado global |
| **[Vue Router](https://router.vuejs.org)** | 4.4+ | Enrutamiento SPA |
| **[Laravel Echo](https://laravel.com/docs/broadcasting)** | 2.3+ | Cliente WebSocket |
| **[Pusher JS](https://pusher.com)** | 8.4+ | Cliente Pusher para Reverb |
| **[Axios](https://axios-http.com)** | 1.13+ | Cliente HTTP |

### Arquitectura
- **RESTful API**: Comunicación backend-frontend mediante API JSON
- **SPA (Single Page Application)**: Experiencia de usuario fluida sin recargas
- **Arquitectura Modular**: Composables Vue para lógica reutilizable
- **Broadcasting de Eventos**: Arquitectura orientada a eventos en tiempo real

---

## 📋 Requisitos Previos

Asegúrate de tener instalado lo siguiente:

### Opción 1: Instalación Local
- **PHP** >= 8.2
- **Composer** (gestor de dependencias PHP)
- **Node.js** >= 18.x & **NPM**
- **MySQL** >= 8.0 o **SQLite**

### Opción 2: Docker (Recomendado)
- **Docker Desktop** (incluye Docker y Docker Compose)
- **WSL2** (si estás en Windows)

---

## ⚙️ Instalación

### 🐳 Con Docker (Laravel Sail) - RECOMENDADO

Esta es la forma más sencilla de ejecutar el proyecto con todas las dependencias aisladas.

#### 1. Clonar el repositorio
```bash
git clone https://github.com/tu-usuario/teamboard.git
cd teamboard
```

#### 2. Instalar dependencias de Composer (sin PHP local)
```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php85-composer:latest \
    composer install --ignore-platform-reqs
```

#### 3. Configurar el archivo `.env`
```bash
cp .env.example .env
```

Edita `.env` y configura para Docker:
```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=sync

REVERB_APP_ID=244029
REVERB_APP_KEY=s2d7glnglkodpxkzjgy9
REVERB_APP_SECRET=unauvoccsxds5vi0ka3c
REVERB_HOST="0.0.0.0"
REVERB_PORT=8090
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8090
VITE_REVERB_SCHEME=http

# Opcional: Clave de Gemini API para funciones de IA
GEMINI_API_KEY=tu_clave_aqui
```

#### 4. Generar clave de aplicación
```bash
./vendor/bin/sail artisan key:generate
```

#### 5. Levantar los contenedores
```bash
./vendor/bin/sail up -d
```

Esto levantará:
- ✅ Servidor Laravel (puerto 80)
- ✅ MySQL (puerto 3355)
- ✅ Redis (puerto 6379)
- ✅ Vite Dev Server (puerto 5175)

#### 6. Ejecutar migraciones y seeders
```bash
./vendor/bin/sail artisan migrate --seed
```

Esto creará:
- 3 usuarios de prueba: `test@example.com`, `alice@example.com`, `bob@example.com` (password: `password`)
- Estructura de base de datos completa

#### 7. Instalar dependencias de Node.js y ejecutar Vite
```bash
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

#### 8. Iniciar Laravel Reverb (WebSockets)
En otra terminal:
```bash
./vendor/bin/sail artisan reverb:start
```

#### 9. Acceder a la aplicación
🌐 **Frontend**: http://localhost:5175  
🔧 **Backend API**: http://localhost/api

**Credenciales de prueba**:
- Email: `test@example.com`
- Password: `password`

---

### 💻 Instalación Local (Sin Docker)

Si prefieres no usar Docker:

#### 1. Clonar e instalar dependencias
```bash
git clone https://github.com/tu-usuario/teamboard.git
cd teamboard
composer install
npm install
```

#### 2. Configurar entorno
```bash
cp .env.example .env
php artisan key:generate
```

Edita `.env` con tu configuración local de MySQL o usa SQLite:
```env
DB_CONNECTION=sqlite
# DB_DATABASE=/ruta/absoluta/a/database.sqlite

# O para MySQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=teamboard
# DB_USERNAME=tu_usuario
# DB_PASSWORD=tu_password
```

#### 3. Crear base de datos (si usas SQLite)
```bash
touch database/database.sqlite
```

#### 4. Ejecutar migraciones
```bash
php artisan migrate --seed
```

#### 5. Ejecutar servicios (necesitarás 3 terminales)

**Terminal 1 - Laravel Server:**
```bash
php artisan serve
```

**Terminal 2 - Laravel Reverb (WebSockets):**
```bash
php artisan reverb:start
```

**Terminal 3 - Vite Dev Server:**
```bash
npm run dev
```

#### 6. Acceder a la aplicación
🌐 http://localhost:8000

---

## 📂 Estructura del Proyecto

```
Teamboard/
├── app/
│   ├── Events/                      # Eventos de Broadcasting en tiempo real
│   │   ├── BoardCreated.php         # Nuevo tablero creado
│   │   ├── BoardRefreshed.php       # Tablero actualizado (IA global)
│   │   ├── CardCreated.php          # Nueva tarjeta creada
│   │   ├── CardUpdated.php          # Tarjeta actualizada
│   │   ├── CardMoved.php            # Tarjeta movida de columna
│   │   ├── CardDeleted.php          # Tarjeta eliminada
│   │   ├── ColumnCreated.php        # Nueva columna creada
│   │   ├── ColumnReordered.php      # Columnas reordenadas
│   │   └── CommentAdded.php         # Comentario agregado
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── ProjectController.php      # CRUD de proyectos/tableros
│   │   │       ├── BoardColumnController.php  # CRUD de columnas
│   │   │       ├── CardController.php         # CRUD de tarjetas y tags
│   │   │       ├── CommentController.php      # Agregar comentarios
│   │   │       ├── ProjectMemberController.php # Gestión de miembros
│   │   │       ├── AiController.php           # Endpoints de IA (Harvis)
│   │   │       ├── AuthController.php         # Login/Registro
│   │   │       └── ProfileController.php      # Actualizar perfil
│   │   │
│   │   └── Requests/                # Form Requests (validación)
│   │       └── Ai/
│   │           ├── AiColumnRequest.php
│   │           └── AiGlobalRequest.php
│   │
│   ├── Models/
│   │   ├── User.php                 # Usuario del sistema
│   │   ├── Project.php              # Proyecto/Tablero
│   │   ├── BoardColumn.php          # Columna de un tablero
│   │   ├── Card.php                 # Tarjeta/Tarea
│   │   ├── Tag.php                  # Etiqueta
│   │   └── Comment.php              # Comentario de tarjeta
│   │
│   ├── Services/
│   │   └── Ai/
│   │       └── HarvisService.php    # Lógica de IA (Gemini API)
│   │
│   └── Notifications/
│       └── UserAssignedToCard.php   # Notificación de asignación
│
├── database/
│   ├── migrations/                  # Migraciones de base de datos
│   │   ├── *_create_users_table.php
│   │   ├── *_create_projects_table.php
│   │   ├── *_create_board_columns_table.php
│   │   ├── *_create_cards_table.php
│   │   ├── *_create_tags_table.php
│   │   ├── *_create_card_tag_table.php
│   │   ├── *_create_project_user_table.php
│   │   ├── *_create_comments_table.php
│   │   ├── *_add_assignee_id_to_cards_table.php
│   │   ├── *_add_priority_to_cards_table.php
│   │   ├── *_add_due_date_to_cards_table.php
│   │   └── ...
│   │
│   └── seeders/
│       ├── DatabaseSeeder.php       # Crea usuarios de prueba
│       ├── TagSeeder.php            # Etiquetas por defecto
│       └── DevProjectSeeder.php     # Proyecto de desarrollo
│
├── resources/
│   ├── js/
│   │   ├── components/
│   │   │   ├── layout/
│   │   │   │   ├── SidebarBoards.vue       # Barra lateral con proyectos
│   │   │   │   └── BoardTopbar.vue         # Barra superior del tablero
│   │   │   │
│   │   │   ├── kanban/
│   │   │   │   ├── KanbanBoard.vue         # Contenedor principal del tablero
│   │   │   │   └── KanbanColumn.vue        # Columna con drag & drop
│   │   │   │
│   │   │   └── modals/
│   │   │       ├── CardModalEdit.vue       # Modal de edición de tarjeta
│   │   │       └── ProfileModalEdit.vue    # Modal de perfil de usuario
│   │   │
│   │   ├── composables/             # Lógica reutilizable (Vue Composition API)
│   │   │   └── board/
│   │   │       ├── useBoardData.js          # Carga de proyectos y tableros
│   │   │       ├── useBoardRealtime.js      # Suscripción a eventos WebSocket
│   │   │       ├── useCardEditor.js         # Lógica de edición de tarjetas
│   │   │       ├── useCardDnD.js            # Drag & Drop de tarjetas
│   │   │       └── useColumnDnD.js          # Drag & Drop de columnas
│   │   │
│   │   ├── lib/
│   │   │   ├── http.js              # Cliente Axios configurado
│   │   │   └── echo.js              # Cliente Laravel Echo (WebSockets)
│   │   │
│   │   ├── pages/
│   │   │   ├── Login.vue            # Página de inicio de sesión
│   │   │   ├── Register.vue         # Página de registro
│   │   │   └── Board.vue            # Página principal del tablero
│   │   │
│   │   ├── router/
│   │   │   └── index.js             # Configuración de Vue Router
│   │   │
│   │   ├── services/
│   │   │   └── boardApi.js          # Funciones de API (proyectos, tarjetas...)
│   │   │
│   │   ├── stores/
│   │   │   └── auth.js              # Store de Pinia para autenticación
│   │   │
│   │   ├── assets/
│   │   │   └── main.css             # Estilos globales
│   │   │
│   │   ├── App.vue                  # Componente raíz
│   │   └── app.js                   # Entry point de Vue
│   │
│   └── views/
│       └── app.blade.php            # Template HTML principal
│
├── routes/
│   ├── web.php                      # Rutas web (SPA fallback)
│   ├── api.php                      # Rutas de API REST
│   ├── channels.php                 # Canales de Broadcasting
│   └── console.php                  # Comandos Artisan
│
├── config/
│   ├── broadcasting.php             # Configuración de Reverb
│   ├── openai.php                   # Configuración de Gemini API
│   └── ...
│
├── compose.yaml                     # Docker Compose (Laravel Sail)
├── vite.config.js                   # Configuración de Vite
├── package.json                     # Dependencias de Node.js
├── composer.json                    # Dependencias de PHP
└── .env                             # Variables de entorno
```

---

## 🔗 Endpoints de la API

### Autenticación
| Método | Ruta | Descripción |
|--------|------|-------------|
| `POST` | `/api/register` | Registrar nuevo usuario |
| `POST` | `/api/login` | Iniciar sesión |
| `POST` | `/api/logout` | Cerrar sesión |
| `GET` | `/api/user` | Obtener usuario autenticado |

### Proyectos/Tableros
| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/api/projects` | Listar proyectos del usuario |
| `POST` | `/api/projects` | Crear nuevo proyecto |
| `GET` | `/api/projects/{id}/board` | Obtener tablero completo con columnas y tarjetas |
| `PATCH` | `/api/projects/{id}` | Actualizar nombre de proyecto |
| `DELETE` | `/api/projects/{id}` | Eliminar proyecto |

### Columnas
| Método | Ruta | Descripción |
|--------|------|-------------|
| `POST` | `/api/projects/{id}/columns` | Crear columna |
| `PATCH` | `/api/columns/{id}` | Actualizar nombre de columna |
| `DELETE` | `/api/columns/{id}` | Eliminar columna |
| `PATCH` | `/api/projects/{id}/columns/reorder` | Reordenar columnas |

### Tarjetas
| Método | Ruta | Descripción |
|--------|------|-------------|
| `POST` | `/api/cards` | Crear tarjeta |
| `PATCH` | `/api/cards/{id}` | Actualizar tarjeta (título, descripción, prioridad, fecha, asignado) |
| `PATCH` | `/api/cards/{id}/move` | Mover tarjeta a otra columna/posición |
| `DELETE` | `/api/cards/{id}` | Eliminar tarjeta |
| `POST` | `/api/cards/{id}/tags` | Agregar etiqueta a tarjeta |
| `DELETE` | `/api/cards/{id}/tags/{tag}` | Quitar etiqueta de tarjeta |

### Etiquetas
| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/api/tags` | Listar todas las etiquetas |

### Comentarios
| Método | Ruta | Descripción |
|--------|------|-------------|
| `POST` | `/api/cards/{id}/comments` | Agregar comentario a tarjeta |

### Miembros de Proyecto
| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/api/projects/{id}/members` | Listar miembros del proyecto |
| `POST` | `/api/projects/{id}/members` | Agregar miembro por email |
| `DELETE` | `/api/projects/{id}/members/{user}` | Eliminar miembro |

### Inteligencia Artificial (Harvis)
| Método | Ruta | Descripción |
|--------|------|-------------|
| `POST` | `/api/ai/handle` | Procesar comando de IA en una columna específica |
| `POST` | `/api/ai/global` | Procesar comando de IA a nivel de proyecto completo |

### Notificaciones
| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/api/notifications` | Obtener notificaciones no leídas |
| `POST` | `/api/notifications/{id}/read` | Marcar notificación como leída |
| `POST` | `/api/notifications/read-all` | Marcar todas como leídas |

### Perfil
| Método | Ruta | Descripción |
|--------|------|-------------|
| `POST` | `/api/profile/update` | Actualizar perfil (nombre, email, avatar) |

---

## 🎨 Características de la Interfaz

### 🎯 Página de Login/Registro
- Diseño moderno con efectos glassmorphism
- Animaciones suaves y gradientes
- Validación de formularios en tiempo real

### 📊 Dashboard Principal
- **Sidebar**: Lista de todos tus proyectos con scroll
- **Topbar**: Nombre del proyecto actual, botones de acción (nueva columna, añadir miembros, perfil)
- **Área Principal**: Tablero Kanban con todas las columnas y tarjetas

### 🃏 Tarjetas Interactivas
- Vista compacta: título, prioridad (color), etiquetas y avatar del asignado
- Drag & Drop fluido entre columnas
- Hover effects y animaciones
- Modal de edición completo al hacer clic

### 🤖 Asistente Harvis
- **Modo Columna**: Trabaja sobre una columna específica
  - Ejemplo: "Crea 3 tareas para implementar el login"
  - Ejemplo: "Reordena estas tareas por prioridad"
- **Modo Global**: Analiza todo el proyecto
  - Ejemplo: "Organiza las tareas por módulo"
  - Ejemplo: "Crea un sprint de 2 semanas"

### 🎨 Sistema de Etiquetas
- Paleta de colores predefinida
- Creación dinámica de nuevas etiquetas
- Filtrado visual de tarjetas

### 👤 Gestión de Miembros
- Buscar usuarios por email
- Asignar tarjetas a miembros
- Ver avatares en tarjetas

---

## 🔌 Eventos en Tiempo Real

### Canales de Broadcasting

#### `project.{projectId}`
Todos los eventos del tablero:
- `BoardCreated`: Nuevo tablero creado
- `BoardRefreshed`: Recarga completa del tablero (después de IA global)
- `ColumnCreated`: Nueva columna añadida
- `ColumnReordered`: Columnas reordenadas
- `CardCreated`: Nueva tarjeta creada
- `CardUpdated`: Tarjeta actualizada (título, descripción, etc.)
- `CardMoved`: Tarjeta movida entre columnas
- `CardDeleted`: Tarjeta eliminada
- `CommentAdded`: Nuevo comentario en tarjeta

#### `boards.{userId}`
Eventos de la sidebar:
- `BoardCreated`: Aparece nuevo tablero en sidebar de todos los usuarios

#### `App.Models.User.{id}`
Eventos privados del usuario:
- Notificaciones de asignación a tarjetas

---

## 🧪 Testing

Ejecutar tests:
```bash
# Con Sail
./vendor/bin/sail artisan test

# Local
php artisan test
```

---

## 🌍 Variables de Entorno Importantes

| Variable | Descripción | Ejemplo |
|----------|-------------|---------|
| `APP_URL` | URL del backend | `http://localhost` |
| `FRONTEND_URL` | URL del frontend | `http://localhost:5175` |
| `VITE_BACKEND_URL` | URL del backend para Vite | `http://localhost` |
| `DB_CONNECTION` | Tipo de base de datos | `mysql` o `sqlite` |
| `DB_HOST` | Host de la base de datos | `mysql` (Docker) o `127.0.0.1` (local) |
| `BROADCAST_CONNECTION` | Conexión de broadcasting | `reverb` |
| `REVERB_APP_KEY` | Clave de la app Reverb | `s2d7glnglkodpxkzjgy9` |
| `REVERB_HOST` | Host del servidor Reverb | `0.0.0.0` |
| `REVERB_PORT` | Puerto del servidor Reverb | `8090` |
| `VITE_REVERB_HOST` | Host para el cliente Echo | `localhost` |
| `VITE_REVERB_PORT` | Puerto para el cliente Echo | `8090` |
| `GEMINI_API_KEY` | Clave de API de Google Gemini | `AIzaSy...` |
| `SESSION_DOMAIN` | Dominio de las cookies | `localhost` |
| `SANCTUM_STATEFUL_DOMAINS` | Dominios permitidos para Sanctum | `localhost,localhost:5175` |

---

## 📝 Notas Técnicas

### Arquitectura de Composables
El proyecto usa **Vue 3 Composition API** con composables modulares:

- **`useBoardData`**: Manejo de estado de proyectos, columnas y tarjetas
- **`useBoardRealtime`**: Suscripción y manejo de eventos WebSocket
- **`useCardEditor`**: Lógica del modal de edición de tarjetas
- **`useCardDnD`**: Lógica de drag & drop de tarjetas
- **`useColumnDnD`**: Lógica de drag & drop de columnas

### Sistema de Broadcasting
Laravel Reverb proporciona WebSockets de alto rendimiento. Los eventos se envían con `broadcast()` y se reciben con Laravel Echo en el frontend.

### Autenticación
- **Laravel Sanctum** proporciona autenticación basada en cookies para SPAs
- Las cookies de sesión permiten autenticación sin tokens explícitos
- CSRF protection habilitado

### Inteligencia Artificial
- **Harvis** usa la API de Google Gemini (modelo `gemini-2.5-flash`)
- Contexto limitado para optimizar costos (60 tarjetas por columna, 12 columnas máximo)
- Respuestas en formato JSON estructurado

---

## 🚀 Comandos Útiles

### Laravel Sail
```bash
# Levantar contenedores
./vendor/bin/sail up -d

# Ver logs en tiempo real
./vendor/bin/sail logs -f

# Ejecutar comandos Artisan
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan tinker

# Instalar dependencias
./vendor/bin/sail composer require paquete
./vendor/bin/sail npm install paquete

# Ejecutar tests
./vendor/bin/sail artisan test

# Acceder al contenedor
./vendor/bin/sail shell

# Detener contenedores
./vendor/bin/sail down
```

### Artisan
```bash
# Migraciones
php artisan migrate
php artisan migrate:fresh --seed

# Caché
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Cola de trabajos
php artisan queue:work

# Reverb (WebSockets)
php artisan reverb:start
php artisan reverb:restart
```

### NPM
```bash
# Modo desarrollo
npm run dev

# Build de producción
npm run build
```

---

## 🤝 Contribución

Las contribuciones son bienvenidas. Si deseas contribuir:

1. Haz un **Fork** del proyecto
2. Crea una rama para tu feature (`git checkout -b feature/MiFeature`)
3. Haz commit de tus cambios (`git commit -m 'Add: nueva funcionalidad'`)
4. Haz push a la rama (`git push origin feature/MiFeature`)
5. Abre un **Pull Request**

### Convenciones de Código
- **Backend**: Sigue las convenciones de Laravel (PSR-12)
- **Frontend**: Sigue las convenciones de Vue.js 3
- **Commits**: Usa commits descriptivos en español o inglés

---

## 📄 Licencia

Este proyecto es de código abierto y está disponible bajo la licencia MIT.

---

## 👨‍💻 Autor

Desarrollado por Ana Andronache

---

## 🙏 Agradecimientos

- [Laravel](https://laravel.com) - Framework PHP excepcional
- [Vue.js](https://vuejs.org) - Framework JavaScript progresivo
- [Laravel Reverb](https://reverb.laravel.com) - WebSockets nativos de Laravel
- [Google Gemini](https://ai.google.dev) - API de Inteligencia Artificial

---

## 📞 Soporte

Si tienes problemas o preguntas:
- Abre un **Issue** en GitHub
- Revisa la documentación de [Laravel](https://laravel.com/docs)
- Consulta la guía de [Vue 3](https://vuejs.org/guide)

---

## 🎓 Presentación del Proyecto

Este proyecto ha sido desarrollado como **Trabajo de Fin de Máster (TFM)**

📊 **Presentación TFM**: [Ver slides en Google Presentations](https://docs.google.com/presentation/d/13PLEHS8kC4vXz1Ta7PF89FJw7-E57Zn7QJtgMJZIOFw/edit?usp=sharing)

La presentación incluye:
- 🎯 Objetivos y alcance del proyecto
- 🏗️ Arquitectura técnica detallada
- 💡 Decisiones de tecnologías utilizadas

---

**¡Disfruta organizando tus proyectos con Teamboard! 🎉**
