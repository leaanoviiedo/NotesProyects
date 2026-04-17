# DevOS Pro

**Dashboard de productividad para desarrolladores.** Gestión de proyectos con Kanban, notas colaborativas, calendario, fragmentos de código, tester de APIs y logs de actividad — todo en tiempo real gracias a WebSockets.

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![Livewire](https://img.shields.io/badge/Livewire-4-4E56A6?logo=livewire&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind-v4-06B6D4?logo=tailwindcss&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white)

---

## Funcionalidades

### 📋 Kanban
Tableros por proyecto con columnas y tarjetas personalizables. Arrastrar y soltar (SortableJS), etiquetas de color, prioridades, fechas de vencimiento y presencia en tiempo real (quién está mirando el mismo tablero).

### 📝 Notas
Editor de texto enriquecido (TipTap) con soporte Markdown, categorías, notas fijadas y colaboración en tiempo real: el sistema indica qué usuario está editando qué nota en cada momento.

### 📅 Calendario
Vista de eventos del proyecto, con integración opcional a **Google Calendar**.

### 🗂 Proyectos
Gestión completa de proyectos: miembros con roles (owner / admin / member), permisos granulares por módulo (kanban, notas, calendario), favoritos y archivo.

### 🔗 Links compartidos
Genera enlaces de acceso público a cualquier proyecto con permisos configurables (sólo kanban, sólo notas, o ambos) y fecha de expiración opcional. Usuarios autenticados pueden guardar proyectos compartidos.

### 🧩 Fragmentos de código
Biblioteca personal de snippets con resaltado de sintaxis (highlight.js + lowlight), filtro por lenguaje y marcado de favoritos.

### 🔌 Tester de APIs
Cliente HTTP integrado para probar endpoints sin salir del dashboard.

### 📊 Actividad & Logs
Registro de actividad por proyecto. Los logs se pueden convertir directamente en tarjetas Kanban.

### ⚡ Tiempo real
Toda la colaboración usa **WebSockets vía Laravel Reverb** — sin polling, sin recargas.

---

## Stack tecnológico

| Capa | Tecnología |
|------|-----------|
| Backend | PHP 8.4, Laravel 13, Livewire 4.2 |
| Frontend | Alpine.js, Tailwind CSS v4, Vite |
| Editor | TipTap + lowlight (highlight.js) |
| Drag & Drop | SortableJS |
| WebSockets | Laravel Reverb + Laravel Echo + Pusher.js |
| Base de datos | MySQL 8 |
| Cache / Sesiones | Redis 7 |
| Cola de trabajos | Laravel Queue (database driver) |
| Tareas programadas | Laravel Scheduler (`schedule:work`) |
| Servidor web | Nginx + PHP-FPM |
| Process manager | Supervisord |
| Infraestructura | Docker (multi-stage, ARM64) |

---

## Despliegue con Docker

### Inicio rápido (zero-config)

No se necesita crear ningún `.env`. El contenedor genera y persiste automáticamente `APP_KEY`, `REVERB_APP_KEY` y `REVERB_APP_SECRET` en un volumen Docker.

```bash
# 1. Clonar el repositorio
git clone https://github.com/leaanoviiedo/NotesProyects.git
cd NotesProyects

# 2. Construir y levantar todos los servicios
docker compose up -d --build

# 3. Ver logs del arranque (migraciones, generación de claves, etc.)
docker compose logs -f app
```

La aplicación queda disponible en:
- **Web:** `http://localhost:8003`
- **WebSocket Reverb:** `ws://localhost:8081`

### Servicios

| Servicio | Imagen | Descripción |
|----------|--------|-------------|
| `app` | build local | Nginx · PHP-FPM · Reverb · Queue workers × 2 · Scheduler |
| `db` | `mysql:8.0` | Base de datos principal |
| `redis` | `redis:7-alpine` | Cache y sesiones |

### Despliegue con Portainer (Raspberry Pi / ARM64)

1. En Portainer, crear un nuevo **Stack** apuntando a la URL del repositorio.
2. Asegurarse de que el compose tiene `pull_policy: build` — la imagen se construye localmente, no se descarga de ningún registry.
3. En cada actualización: **Stack → Update → Force rebuild**.

> **Nota:** Si el volumen `db_data` es de un despliegue anterior con credenciales distintas, eliminarlo antes de recrear el stack para que MySQL reinicialice la base de datos.

### Comandos útiles

```bash
# Estado de los procesos internos (nginx, php-fpm, reverb, workers, scheduler)
docker compose exec app supervisorctl status

# Ejecutar migraciones manualmente
docker compose exec app php artisan migrate

# Abrir tinker
docker compose exec app php artisan tinker

# Logs en tiempo real
docker compose logs -f app

# Reiniciar el servidor WebSocket sin reiniciar el contenedor
docker compose exec app supervisorctl restart reverb
```

### Arquitectura interna del contenedor

```
┌─────────────────────────────────────────────┐
│  Docker container: app                       │
│                                              │
│  supervisord                                 │
│  ├── nginx          (puerto 80)              │
│  ├── php-fpm        (127.0.0.1:9000)         │
│  ├── reverb         (puerto 8080 interno)    │
│  ├── queue-worker   × 2                      │
│  └── scheduler      (schedule:work)          │
└─────────────────────────────────────────────┘
```

### CI con GitHub Actions

El pipeline (`.github/workflows/deploy.yml`) ejecuta la suite de tests en cada `push` y `pull_request` a `main`.

#### Secrets necesarios en GitHub

| Secret | Descripción |
|--------|-------------|
| `DEPLOY_HOST` | IP o dominio del servidor |
| `DEPLOY_USER` | Usuario SSH |
| `DEPLOY_SSH_KEY` | Clave privada SSH |
| `DEPLOY_PATH` | Ruta del proyecto en el servidor |

---

## Desarrollo local (sin Docker)

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
