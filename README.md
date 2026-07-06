# SecureWallet — INF781 · Seguridad de Software

Billetera electrónica segura desarrollada como examen final de INF781 (UATF · Ingeniería
Informática). Arquitectura desacoplada: **backend Laravel 13 (API REST)** + **frontend
React (SPA)**, comunicándose por HTTP/JSON, con base de datos **PostgreSQL**.

Repositorio con dos carpetas principales:

```
securewallet/
├── backend/     # API REST — Laravel 13 + PHP 8.3, JWT propio, MFA/TOTP, RBAC
├── frontend/    # SPA — React + Vite
└── postman/     # Colección Postman con peticiones válidas, inválidas y de ataque
```

---

## 1. Requisitos previos

- PHP >= 8.3 con extensiones: `pdo_pgsql`, `mbstring`, `openssl`, `bcmath`
- Composer 2.x
- Node.js >= 18 y npm
- PostgreSQL >= 14
- (Windows) Laragon, XAMPP o similar con PostgreSQL habilitado

---

## 2. Instalación y ejecución — Backend

```bash
cd backend

# 1. Instalar dependencias PHP
composer install

# 2. Copiar variables de entorno
cp .env.example .env

# 3. Generar la clave de aplicación de Laravel
php artisan key:generate

# 4. Generar un JWT_SECRET propio y pegarlo en .env
php -r "echo bin2hex(random_bytes(32));"

# 5. Crear la base de datos en PostgreSQL (nombre por defecto: securewallet)
#    CREATE DATABASE securewallet;

# 6. Ejecutar migraciones y cargar usuarios semilla
php artisan migrate --seed

# 7. Levantar el servidor
php artisan serve
```

API disponible en: **http://localhost:8000/api/v1**

Si necesitas reiniciar todo desde cero (por ejemplo, tras pruebas que dejaron datos
inconsistentes):

```bash
php artisan migrate:fresh --seed
```

---

## 3. Instalación y ejecución — Frontend

```bash
cd frontend

# 1. Instalar dependencias
npm install

# 2. Copiar variables de entorno
cp .env.example .env

# 3. Levantar el servidor de desarrollo
npm run dev
```

Frontend disponible en: **http://localhost:5173**

---

## 4. Variables de entorno

### `backend/.env.example`

```env
APP_NAME=SecureWallet
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=America/La_Paz

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=securewallet
DB_USERNAME=postgres
DB_PASSWORD=postgres

SESSION_DRIVER=file
# IMPORTANTE (RF-10): usar 'file' o 'database', NUNCA 'array', para que la blacklist de
# access tokens revocados por logout persista entre peticiones.
CACHE_STORE=file
QUEUE_CONNECTION=sync

# Secreto propio para firmar los access tokens JWT (RS-07).
# Generar con: php -r "echo bin2hex(random_bytes(32));"
JWT_SECRET=CAMBIAR_ESTE_VALOR_POR_UNO_ALEATORIO_DE_64_HEX

# CORS (RS-06): origen EXACTO del frontend, nunca "*"
FRONTEND_URL=http://localhost:5173

# reCAPTCHA (RS-08). Vacío en desarrollo omite la verificación real de captcha.
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET=
```

### `frontend/.env.example`

```env
VITE_API_URL=http://localhost:8000/api/v1
```

> **Nunca subas tu `.env` real al repositorio.** Solo los `.env.example` deben versionarse.

---

## 5. Configuración avanzada (ya incluida en este repositorio)

Este proyecto ya trae configurados y versionados (no requiere pasos manuales adicionales):

- `config/app.php` → clave `jwt_secret` para firmar los JWT (RS-07)
- `config/services.php` → credenciales de reCAPTCHA (RS-08)
- `config/cors.php` → origen restringido al frontend (RS-06)
- `bootstrap/app.php` → middlewares `jwt.auth`, `role` y `ForceJsonErrors` registrados
- `routes/api.php` → todas las rutas de la API bajo `/api/v1`

Solo necesitas completar los valores de tu `.env` (ver sección 4); no hace falta tocar
ningún archivo de `config/` ni `bootstrap/`.

---

## 6. Usuarios semilla

Creados automáticamente por `php artisan migrate --seed`:

| Rol   | Email                        | Contraseña         | MFA             | Saldo inicial |
| ----- | ---------------------------- | ------------------ | --------------- | ------------- |
| ADMIN | `admin@securewallet.test`    | `Admin#2026Seguro` | No              | Bs. 0.00      |
| USER  | `usuario1@securewallet.test` | `Usuario1#2026`    | No              | Bs. 500.00    |
| USER  | `usuario2@securewallet.test` | `Usuario2#2026`    | **Sí (activo)** | Bs. 1000.00   |

Al correr el seeder, la consola imprime el **secreto TOTP** de `usuario2` para agregarlo
manualmente (modo "clave manual") en Google Authenticator, Authy o FreeOTP — necesario para
iniciar sesión con ese usuario (RF-03) y para confirmar transferencias mayores a Bs. 500 (RF-07).

---

## 7. Mapeo de requerimientos funcionales (RF-01 a RF-10)

| RF    | Descripción                                                 | Dónde está implementado                                                                 |
| ----- | ----------------------------------------------------------- | --------------------------------------------------------------------------------------- |
| RF-01 | Registro + billetera automática                             | `AuthController::register`, migración `wallets`                                         |
| RF-02 | Login + bloqueo 5 intentos/15 min                           | `AuthController::login` (`failed_login_attempts`, `locked_until`)                       |
| RF-03 | MFA/TOTP con Google Authenticator                           | `TotpService`, `AuthController::mfaEnable/mfaConfirm/mfaVerify`, `MfaSetup.jsx`         |
| RF-04 | Consulta de saldo/perfil                                    | `WalletController::me/show`                                                             |
| RF-05 | Recarga simulada                                            | `WalletController::topup`                                                               |
| RF-06 | Transferencia con límites (1–5000 Bs)                       | `TransferController::store`                                                             |
| RF-07 | Confirmación en 2 pasos + TOTP si > 500 Bs                  | `TransferController::confirm`, `Transfer.jsx`                                           |
| RF-08 | Historial paginado y filtrable                              | `WalletController::history`                                                             |
| RF-09 | Panel admin (solo rol ADMIN)                                | `AdminController`, `Admin.jsx`                                                          |
| RF-10 | Logout invalida access token Y refresh token en el servidor | `AuthController::logout` → blacklist en caché + `RefreshTokenService::revokeAllForUser` |

## 8. Mapeo de controles de seguridad (RS-01 a RS-10)

| RS    | OWASP                      | Implementación                                                                                                                                                            |
| ----- | -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| RS-01 | A01 Broken Access Control  | UUID como clave pública (`getRouteKeyName`), todo acceso a wallet/transacciones usa el usuario autenticado, nunca un ID recibido en la URL                                |
| RS-02 | A01                        | `RoleMiddleware` (`role:ADMIN`), verificado en servidor, rutas admin agrupadas                                                                                            |
| RS-03 | A02 Cryptographic Failures | cast `password => 'hashed'` (bcrypt), `mfa_secret => 'encrypted'` + `hidden` en el modelo `User`                                                                          |
| RS-04 | A03 Injection              | Eloquent ORM (parámetros enlazados), Form Requests con `validated()` explícito, nunca `$request->all()`                                                                   |
| RS-05 | A04 Insecure Design        | `DB::transaction` + `lockForUpdate()` en topup/transfer/confirm, columna `idempotency_key` única                                                                          |
| RS-06 | A05 Security Misconfig.    | `config/cors.php` con origen exacto, `ForceJsonErrors` middleware, `.env` fuera del repo                                                                                  |
| RS-07 | A07 Auth Failures          | Access token JWT de 15 min con `jti`, refresh token con rotación + detección de reúso (tabla `refresh_tokens`, `family_id`), política de contraseñas en `RegisterRequest` |
| RS-08 | A07                        | `RateLimiter::for('login'/'transfers')`, CAPTCHA en `AuthController::register`                                                                                            |
| RS-09 | A09 Logging                | `AuditLogService` + tabla `audit_logs` (solo INSERT), consultable solo por ADMIN                                                                                          |
| RS-10 | XSS/CSRF                   | React escapa salida por defecto, token en `sessionStorage` con justificación documentada en `client.js`                                                                   |

---

## 9. Credenciales de demo rápidas

```
ADMIN  → admin@securewallet.test     / Admin#2026Seguro
USER1  → usuario1@securewallet.test  / Usuario1#2026      (sin MFA)
USER2  → usuario2@securewallet.test  / Usuario2#2026      (con MFA — usar secreto TOTP impreso al hacer seed)
```

---

## 10. Colección Postman

En `/postman/SecureWallet.postman_collection.json`: peticiones válidas e inválidas para todos
los endpoints, más casos de ataque (BOLA/IDOR, RBAC, fuerza bruta) usados para verificar los
controles RS-01 a RS-10 durante la defensa oral.

---
