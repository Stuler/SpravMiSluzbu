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
- **DevOps**: TBD: dockerize the project

---

## 📦 Installation

1. clone the repository

```bash
git clone https://github.com/Stuler/SpravMiSluzbu
```

2. install dependencies

```bash
composer install
npm install
```

3. create a copy of `config/local.neon.example` and rename it to `local.neon`

4. setup a database (ask for dump) and update the `local.neon` file with your database credentials
5. build the frontend

```bash
npm run build
```

6. run the application

```bash
php -S localhost:8000 -t www
```

7. open your browser and navigate to `http://localhost:8000`
