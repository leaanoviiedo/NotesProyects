# NotesProyects

Aplicación Laravel con WebSockets en tiempo real (Laravel Reverb), procesamiento de colas y tareas programadas.

**Stack:** PHP 8.3 · Laravel 13 · Livewire 4 · MySQL 8 · Redis 7 · Reverb · Tailwind CSS v4

---

## Despliegue con Docker

### Requisitos
- Docker >= 24
- Docker Compose >= 2.20

### Inicio rápido

```bash
# 1. Clonar el repositorio
git clone https://github.com/tu-usuario/notesproyects.git
cd notesproyects

# 2. Configurar entorno
cp .env.docker .env
# Editar .env con tus valores (contraseñas, claves Reverb, etc.)

# 3. Levantar todos los servicios
docker compose up -d --build

# 4. Ver logs en tiempo real
docker compose logs -f app
```

La aplicación estará disponible en:
- **Web:** http://localhost
- **WebSocket (Reverb):** ws://localhost:8080

### Servicios incluidos

| Servicio | Descripción |
|----------|-------------|
| `app` | PHP-FPM + Nginx + Queue Worker + Reverb + Scheduler |
| `db` | MySQL 8.0 |
| `redis` | Redis 7 (cache, sesiones) |

### Variables de entorno importantes

| Variable | Descripción |
|----------|-------------|
| `APP_KEY` | Se genera automáticamente si está vacío |
| `REVERB_APP_KEY` | Clave pública de Reverb |
| `REVERB_APP_SECRET` | Secreto de Reverb |
| `DB_PASSWORD` | Contraseña de MySQL |

### Comandos útiles

```bash
# Ejecutar migraciones manualmente
docker compose exec app php artisan migrate

# Abrir tinker
docker compose exec app php artisan tinker

# Ver logs de Reverb
docker compose exec app tail -f storage/logs/reverb-out.log

# Ver logs de colas
docker compose exec app tail -f storage/logs/queue-out.log

# Reiniciar un servicio interno (supervisor)
docker compose exec app supervisorctl restart reverb
docker compose exec app supervisorctl status
```

### CI/CD con GitHub Actions

El pipeline en `.github/workflows/deploy.yml`:
1. **Test** — Ejecuta la suite de tests en cada PR/push
2. **Build** — Construye y publica la imagen en `ghcr.io` (solo en `main`)
3. **Deploy** — Despliega en el servidor via SSH (solo en `main`)

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
