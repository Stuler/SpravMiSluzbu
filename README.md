# 🏗️ Service Marketplace Platform

## 📌 About the Project

This internet platform aims to connect two key areas:

### 1️⃣ For People Seeking a Service

- Post advertisements in the **Looking for a Service** section.
- Ads are publicly accessible to a wide audience.

### 2️⃣ For Service Providers

- List services in the **Offering a Service** section.
- Offers are categorized by **geographical location** and **service type**.

---

## 🚀 Features

✅ Easy posting of service requests
✅ Categorized service provider listings
✅ Geographical filtering of service providers
✅ User-friendly interface

---

## 🔧 Tech Stack

- **Backend**: PHP (Nette)
- **Frontend**: React.js, TypeScript
- **Database**: MySQL
- **DevOps**: Docker Compose

---

## 📦 Local Docker

The project currently targets the `feat/stripe-integration` branch for active development.

```bash
docker compose up --build -d
```

- App: `http://localhost:8082`
- Adminer: `http://localhost:8081`
- Database from host: `127.0.0.1:3307`
- Database inside Docker: host `database`, DB `sprav_mi_sluzbu`, user `root`, password `root`

The first database start imports `db/dump/2025_09_04.sql`. To reimport from scratch:

```bash
docker compose down -v
docker compose up --build -d
```

If `config/local.neon` does not exist, the PHP container copies `config/local.neon.example`.

## 📦 Manual Installation

1. Clone the repository

```bash
git clone https://github.com/Stuler/SpravMiSluzbu
```

2. Install dependencies

```bash
composer install
npm install
```

3. Create a copy of `config/local.neon.example` and rename it to `local.neon`

4. Setup a database and update the `local.neon` file with your database credentials
5. Build the frontend

```bash
npm run build
```

6. Run the application

```bash
php -S localhost:8000 -t www
```

7. Open `http://localhost:8000`

## Deployment Branches

- `dev`: development environment
- `beta`: test environment (there is no remote `test` branch at the moment)
- `main`: production environment

Deployment is configured in `.github/workflows/deploy.yml`. The workflow expects separate `DEV_FTP_*`, `TEST_FTP_*`, and `PROD_FTP_*` secrets.
