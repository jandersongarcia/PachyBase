# PachyBase

<p align="center">
  <img src="assets/logo.png" width="200" alt="PachyBase Logo">
</p>

Open-source **self-hosted backend platform** designed to accelerate API development with automatic CRUD generation and predictable AI-friendly JSON responses.

PachyBase allows developers to bootstrap a complete backend infrastructure in minutes using **Docker**, while keeping full control over the codebase and architecture.

The platform focuses on simplicity, extensibility and compatibility with modern **AI-assisted frontend development workflows**.

---

# Core Philosophy

Modern applications repeatedly rebuild the same backend layers:

* CRUD APIs
* authentication
* database connections
* validation
* pagination
* structured responses
* API documentation

PachyBase automates these tasks while keeping the backend **fully transparent and customizable**.

It is not a low-code tool.

It is a **developer-first backend foundation**.

---

# Key Features

### API-First Architecture

PachyBase is built primarily as an API backend.

Frontends can include:

* web applications
* mobile apps
* AI-generated interfaces
* internal tools
* microservices

---

### Predictable JSON Responses

Every endpoint follows the same structured response format:

```json
{
  "success": true,
  "data": {},
  "meta": {},
  "error": null
}
```

Example response:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Jane Doe"
    }
  ],
  "meta": {
    "pagination": {
      "page": 1,
      "per_page": 20,
      "total": 100
    }
  },
  "error": null
}
```

This predictable structure makes PachyBase especially suitable for **AI-driven UI generation tools**.

---

### Automatic CRUD API Generation

For each database table PachyBase generates REST endpoints automatically.

Example for a `users` table:

```
GET    /api/users
GET    /api/users/{id}
POST   /api/users
PUT    /api/users/{id}
DELETE /api/users/{id}
```

Supported features include:

* pagination
* filtering
* sorting
* validation
* structured responses

---

### Docker-First Environment

PachyBase is designed to run using Docker.

The development stack typically includes:

* PHP runtime
* Nginx
* database engine
* optional cache layer

---
# Get Started

### Quick Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/jandersongarcia/PachyBase.git
   cd PachyBase
   ```

2. **Configure database** (optional - defaults to MySQL)
   Edit `.env` and set your preferred database:
   ```env
   DB_DRIVER=mysql  # or pgsql
   ```

3. **Run setup**
   ```bash
   # Linux/Mac
   ./setup.sh
   
   # Windows
   setup.bat
   ```

4. **Access your API**
   Visit http://localhost:8080
   
   You should see a JSON response with your app configuration:
   ```json
   {
     "name": "PachyBase",
     "status": "running",
     "db_driver": "mysql",
     "db_host": "db",
     "db_port": "3306",
     "db_database": "pachybase"
   }
   ```

That's it! Your backend is ready in minutes.

---
# Project Structure

```
/core
/api
/database
/auth
/modules
/services
/utils
/config
/docker
/public
```

Key files:

```
docker/docker-compose.yml   -> container orchestration
public/index.php            -> application entrypoint
composer.json               -> project configuration
setup.sh                    -> environment bootstrap script
```

---

# Requirements

* Docker
* Docker Compose
* PHP 8+
* Composer

---

# Installation

Clone the repository:

```
git clone https://github.com/jandersongarcia/PachyBase.git
cd PachyBase
```

Configure the database in `.env`:

Edit the `.env` file to choose your database driver:

```env
DB_DRIVER=mysql  # or pgsql
DB_PORT=3306     # 3306 for MySQL, 5432 for PostgreSQL
DB_USERNAME=root # root for MySQL, postgres for PostgreSQL
DB_PASSWORD=root
DB_DATABASE=pachybase
```

Run the setup script (on Windows use `setup.bat`, on Linux/Mac use `./setup.sh`):

```
./setup.sh
```

Or on Windows:

```
setup.bat
```

This will install dependencies, generate the Docker configuration based on your database choice, and start the containers.

Once running, the API will be available locally at http://localhost:8080.

---

# AI Integration

PachyBase exposes machine-readable endpoints to help AI systems understand the backend.

Example endpoints:

```
/ai/schema
/openapi.json
```

These endpoints allow tools to:

* generate forms automatically
* build admin dashboards
* generate SDKs
* assist frontend generation

---

# Example Use Cases

PachyBase works well for:

* startups building MVPs
* internal tools
* SaaS backends
* microservices
* AI-generated applications

---

# Non-Goals

PachyBase intentionally avoids becoming:

* a visual no-code builder
* a CMS
* a cloud-locked platform
* a heavy enterprise framework

The core objective remains **lightweight backend automation**.

---

# License

MIT License

---

# Author

Created by **Janderson Garcia**

GitHub:

[https://github.com/jandersongarcia](https://github.com/jandersongarcia)