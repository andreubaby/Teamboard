# Teamboard
**Teamboard** es una aplicación de gestión de proyectos estilo Kanban colaborativa y en tiempo real. Permite a los equipos organizar tareas visualmente, mover tarjetas entre columnas mediante arrastrar y soltar (Drag & Drop), y aprovechar la Inteligencia Artificial para generar y organizar tareas automáticamente.
## 🚀 Características Principales
*   **Tableros Kanban**: Creación de proyectos con columnas y tarjetas personalizables.
*   **Colaboración en Tiempo Real**: Los cambios en el tablero (movimientos, ediciones, creación de tareas) se reflejan instantáneamente en las pantallas de todos los usuarios conectados gracias a **Laravel Reverb**.
*   **Drag & Drop**: Interfaz fluida para reorganizar tareas y columnas.
*   **Asistente de IA (Harvis)**: Integración con OpenAI para generar tareas, sugerir descripciones y organizar el trabajo automáticamente.
*   **Gestión de Usuarios**: Autenticación segura y roles de usuario.
*   **Etiquetado y Priorización**: Clasificación de tareas mediante etiquetas y niveles de prioridad.
## 🛠️ Stack Tecnológico
### Backend
*   **Framework**: [Laravel 11](https://laravel.com) (PHP 8.2+)
*   **Base de Datos**: MySQL
*   **Real-time (WebSockets)**: [Laravel Reverb](https://reverb.laravel.com)
*   **API**: RESTful API con autenticación Sanctum
*   **AI**: [OpenAI PHP Client](https://github.com/openai-php/laravel)
### Frontend
*   **Framework**: [Vue.js 3](https://vuejs.org) (Composition API)
*   **Build Tool**: [Vite](https://vitejs.dev)
*   **Estado Global**: [Pinia](https://pinia.vuejs.org)
*   **Estilos**: Custom CSS (Arquitectura modular)
*   **Comunicación Real-time**: Laravel Echo + Pusher JS
## 📋 Requisitos Previos
Asegúrate de tener instalado lo siguiente en tu entorno de desarrollo:
*   **PHP** >= 8.2
*   **Composer**
*   **Node.js** & **NPM**
*   **SQLite** (Opcional si usas MySQL)
## ⚙️ Instalación
Sigue estos pasos para configurar el proyecto localmente:
1.  **Clonar el repositorio**
    ```bash
    git clone https://github.com/tu-usuario/teamboard.git
    cd teamboard
    ```
2.  **Instalar dependencias de Backend**
    ```bash
    composer install
    ```
3.  **Instalar dependencias de Frontend**
    ```bash
    npm install
    ```
4.  **Configurar variables de entorno**
    Copia el archivo de ejemplo `.env.example` a `.env`:
    ```bash
    cp .env.example .env
    ```
    Genera la clave de la aplicación:
    ```bash
    php artisan key:generate
    ```
    Configura tu base de datos en el archivo `.env`. Si usas SQLite, asegúrate de crear el archivo:
    ```bash
    touch database/database.sqlite
    ```
    Configura tu clave de API de OpenAI (si deseas usar las funciones de IA):
    ```env
    OPENAI_API_KEY=tu_clave_aqui
    ```
5.  **Ejecutar migraciones y seeders**
    Crea las tablas en la base de datos y añade datos de prueba:
    ```bash
    php artisan migrate --seed
    ```
6.  **Compilar activos del Frontend**
    ```bash
    npm run build
    ```
## ▶️ Ejecución
Para iniciar todos los servicios necesarios para el desarrollo local, necesitarás varias terminales:
1.  **Servidor de Laravel**
    ```bash
    php artisan serve
    ```
2.  **Servidor de Reverb (WebSockets)**
    ```bash
    php artisan reverb:start
    ```
3.  **Servidor de Desarrollo de Vite (Frontend)**
    ```bash
    npm run dev
    ```
Accede a la aplicación en: `http://localhost:8000`
## 📂 Estructura del Proyecto
```
Teamboard/
├── app/
│   ├── Events/             # Eventos de Broadcasting (Real-time)
│   ├── Http/Controllers/   # Controladores de la API
│   ├── Models/             # Modelos Eloquent (Project, Card, Column...)
│   └── Services/           # Lógica de negocio (AI Service)
├── resources/
│   ├── js/
│   │   ├── components/     # Componentes Vue reutilizables
│   │   ├── composables/    # Lógica reactiva (Hooks)
│   │   ├── lib/            # Configuración de librerías (Echo, Axios)
│   │   ├── pages/          # Vistas principales de la aplicación
│   │   ├── stores/         # Stores de Pinia
│   │   └── assets/         # Estilos CSS y recursos estáticos
├── routes/
│   ├── api.php             # Rutas de la API Backend
│   └── web.php             # Rutas Web y punto de entrada SPA
└── database/               # Migraciones y Seeders
```
## 🐋 Ejecución con Docker (Laravel Sail)
Si prefieres usar Docker, el proyecto está configurado con Laravel Sail:
```bash
./vendor/bin/sail up -d
./vendor/bin/sail npm run dev
```
Recuerda configurar `DB_HOST=mysql` en tu `.env` si usas Sail.
## 🤝 Contribución
1.  Haz un Fork del proyecto
2.  Crea una rama para tu característica (`git checkout -b feature/AmazingFeature`)
3.  Haz Commit de tus cambios (`git commit -m 'Add some AmazingFeature'`)
4.  Haz Push a la rama (`git push origin feature/AmazingFeature`)
5.  Abre un Pull Request
