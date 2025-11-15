# Kafka Demo – CodeIgniter 4 (v1.0.0)

A simple demo that integrates Apache Kafka with CodeIgniter 4.  
Features:

- Kafka Producer (CI4)
- Kafka Consumer Worker (CLI)
- Retry Queue + Dead Letter Queue (DLQ)
- MySQL persistence (`orders` table)
- Kafka UI for topic inspection
- Auto-refresh dashboard to view processed orders

---

## 📦 Requirements
- Docker Desktop  
- PHP 8.1+  
- Composer  
- Git  

---

## 🚀 Quick Start

### 1. Clone Repository
```bash
git clone <your-repo-url>
cd <project-folder>
```
### 2. Start Services
Use Docker Compose to start all required services.

```bash
docker compose up -d

```
### 3. Start CodeIgniter Server
```bash
php spark serve --port 8084
```

### 4. Start Kafka Consumer Worker
```bash
php spark kafka:work
```

### 5. Access URLs
```
Produce sample Kafka messages: http://localhost:8084/kafka/order-test

View dashboard (auto-refresh): http://localhost:8084/kafka/dashboard

Access Kafka UI: http://localhost:8080

Access phpMyAdmin: http://localhost:8081
```
### 6. 🗃️ MySQL Details
```bash
Connection
Host: 127.0.0.1
Port: 3307
User: root
Pass: root
DB: testdb
```
### 7. Orders table Schema

```
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(12,2),
    raw_json LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

