# 🚀 DOKUMENTASI INFRASTRUKTUR & ARSITEKTUR - APLIKASI GAMON

**Project:** GAMON Time Capsule Messaging Application  
**Tanggal:** Desember 2025  

---

## 1. 🌐 INFRASTRUKTUR & MEKANISME WEB SAMPAI KE USER

### Flow Komunikasi Web-to-User
```
[User Browser] ──HTTP──► [Docker Host:9080] ──bridge──► [gamon-web Container:80]
                                                            │
                                                            ▼
                                                      [Apache Web Server]
                                                            │
                                                            ▼
                                                       [PHP 8.2 Engine]
                                                            │
                                                            ▼
[gamon-db Container:3306] ◄──SQL──── [Application Logic] ◄──include──── [Controllers/Models]
```

### Detail Infrastruktur
1. **Client Layer**
   - Browser: Chrome, Firefox, Safari, Edge
   - Protocol: HTTP/HTTPS
   - Access: `http://localhost:9080` atau `http://[IP]:9080`

2. **Load Balancing & Proxy**
   - Docker Host: Windows dengan Docker Desktop
   - Port Mapping: `9080:80` (host:container)
   - Network: Bridge driver untuk internal communication

3. **Web Server Layer**
   - Container: `gamon-web`
   - Server: Apache HTTP Server 2.4
   - PHP: Version 8.2 dengan mod_php
   - Document Root: `/var/www/html`

4. **Application Layer**
   - Framework: Custom PHP (no framework)
   - Architecture: MVC Pattern
   - Session: PHP Native Sessions
   - File Upload: Local storage dengan validation

5. **Database Layer**
   - Container: `gamon-db`
   - Database: MySQL 8.0
   - Connection: PDO dengan prepared statements
   - Schema: Auto-initialized via docker-entrypoint-initdb.d

---

## 2. 🔧 SERVICE YANG DIGUNAKAN

### Core Services
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Web Service   │    │ Database Service │    │ Network Service │
│                 │    │                 │    │                 │
│ • Apache 2.4    │◄──►│ • MySQL 8.0     │◄──►│ • Bridge Driver │
│ • PHP 8.2       │    │ • Port 3307     │    │ • Isolation     │
│ • Port 9080     │    │ • Persistence   │    │ • Isolation     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Service Details

#### **Web Service (gamon-web)**
- **Base Image:** `php:8.2-apache`
- **Extensions:** PDO, PDO_MySQL, MySQLi, ZIP
- **Modules:** mod_rewrite, mod_headers, mod_ssl
- **Features:**
  - File upload handling
  - Session management
  - Error logging
  - Security headers

#### **Database Service (gamon-db)**
- **Image:** `mysql:8.0`
- **Configuration:**
  - Character Set: utf8mb4
  - Timezone: System timezone
  - InnoDB Engine: Default
  - Auto-initialization dengan schema SQL

#### **Network Service (gamon-network)**
- **Type:** Bridge Network
- **Features:**
  - Container-to-container communication
  - Service name resolution
  - Traffic isolation
  - Port exposure control

#### **Volume Services**
- **Database Volume:** `db_data` (persistent MySQL data)
- **Source Code Bind:** Development sync
- **Upload Volume:** User file storage

---

## 3. 🛡️ KEAMANAN PROJECT

### Security Layers

#### **A. Container Security**
```
┌─────────────────────────────────────┐
│          CONTAINER SECURITY         │
├─────────────────────────────────────┤
│ • Non-root user (www-data)          │
│ • Minimal base images               │
│ • Network isolation                 │
│ • Resource limits                   │
│ • Read-only filesystem (optional)   │
└─────────────────────────────────────┘
```

#### **B. Application Security**
```php
// Input Validation & Sanitization
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// SQL Injection Prevention
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND password = ?");
$stmt->execute([$email, $hashedPassword]);

// Password Security
$hashedPassword = password_hash($password, PASSWORD_ARGON2ID);

// File Upload Security
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
$maxFileSize = 10 * 1024 * 1024; // 10MB
```

#### **C. Network Security**
- **Port Exposure:** Only necessary ports (9080, 3307)
- **Internal Communication:** Container-to-container via bridge network
- **External Access:** Controlled via port mapping
- **Firewall:** Host-level firewall rules

#### **D. Data Security**
- **Database:** Password-protected MySQL dengan user privileges
- **Sessions:** Secure session configuration
- **File Storage:** Proper file permissions dan validation
- **Backup:** Volume-based data persistence

### Security Checklist
- ✅ **Input Validation:** All user inputs sanitized
- ✅ **SQL Injection:** Prepared statements used
- ✅ **XSS Prevention:** Output encoding implemented
- ✅ **CSRF Protection:** Token-based protection ready
- ✅ **File Upload:** Type dan size validation
- ✅ **Authentication:** Secure password hashing
- ✅ **Authorization:** Role-based access control
- ✅ **Session Security:** Proper session management

---

## 4. 🤝 CARA KELOMPOK BERKONTRIBUSI (GITHUB)

### Git Workflow & Collaboration

#### **Repository Structure**
```
gamon-project/
├── main branch (production-ready)
├── develop branch (development)
├── feature/* branches (individual features)
├── hotfix/* branches (urgent fixes)
└── release/* branches (release preparation)
```

#### **Contribution Process**

1. **Setup Repository**
   ```bash
   # Clone repository
   git clone https://github.com/username/gamon-project.git
   cd gamon-project
   
   # Setup development environment
   docker-compose up -d
   ```

2. **Feature Development**
   ```bash
   # Create feature branch
   git checkout -b feature/new-notification-system
   
   # Make changes
   # ... coding ...
   
   # Commit dengan conventional commits
   git commit -m "feat: add real-time notification system"
   git commit -m "fix: resolve database connection timeout"
   git commit -m "docs: update API documentation"
   ```

3. **Pull Request Process**
   ```markdown
   ## Pull Request Template
   
   ### Description
   - Feature: Real-time notification system
   - Changes: Added WebSocket support, notification controller
   
   ### Testing
   - [x] Unit tests passed
   - [x] Integration tests passed
   - [x] Manual testing completed
   
   ### Screenshots
   [Add screenshots if UI changes]
   
   ### Checklist
   - [x] Code follows project standards
   - [x] Documentation updated
   - [x] No breaking changes
   ```

#### **Team Collaboration Rules**

**Branch Protection:**
- `main` branch: Require PR + 1 reviewer approval
- No direct push ke main
- All checks must pass before merge

**Code Review Process:**
1. Developer creates PR
2. Team members review code
3. Address review comments
4. Merge after approval

**Commit Convention:**
```
feat: new feature
fix: bug fix
docs: documentation
style: formatting
refactor: code restructuring
test: adding tests
chore: maintenance tasks
```

#### **Project Management Integration**

**GitHub Features Used:**
- **Issues:** Bug tracking, feature requests
- **Projects:** Kanban board untuk task management
- **Milestones:** Release planning
- **Actions:** CI/CD automation
- **Wiki:** Documentation repository

**Labels & Organization:**
- `bug` - Bug reports
- `enhancement` - Feature requests
- `documentation` - Documentation updates
- `good first issue` - Beginner-friendly tasks
- `priority:high` - Urgent tasks

---

## 5. 📊 MONITORING SERVICE

### Monitoring Stack Architecture
```
┌─────────────────────────────────────────────────────────┐
│                  MONITORING LAYERS                      │
├─────────────────────────────────────────────────────────┤
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────────────┐ │
│  │   Alerts    │ │  Dashboard  │ │     Log Analysis    │ │
│  │             │ │             │ │                     │ │
│  │ • Email     │ │ • Grafana   │ │ • ELK Stack         │ │
│  │ • Slack     │ │ • Custom    │ │ • Log Aggregation   │ │
│  └─────────────┘ └─────────────┘ └─────────────────────┘ │
│                          │                              │
│  ┌─────────────────────────────────────────────────────┐ │
│  │              METRICS COLLECTION                     │ │
│  │                                                     │ │
│  │ • Prometheus (metrics)                              │ │
│  │ • Docker stats (container metrics)                  │ │
│  │ • Application logs (PHP/Apache)                     │ │
│  │ • Database metrics (MySQL)                          │ │
│  └─────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

### Current Monitoring Implementation

#### **Docker Native Monitoring**
```bash
# Container health monitoring
docker ps                    # Container status
docker stats                 # Real-time resource usage
docker-compose logs -f       # Service logs

# Specific service monitoring
docker-compose logs -f web   # Web server logs
docker-compose logs -f db    # Database logs
```

#### **Application-Level Monitoring**
```php
// PHP Error Logging
error_log("User login attempt: " . $email);
error_log("Database connection failed: " . $e->getMessage());

// Performance monitoring
$start_time = microtime(true);
// ... code execution ...
$execution_time = microtime(true) - $start_time;
error_log("Query execution time: " . $execution_time . " seconds");

// Custom metrics logging
function logMetric($metric_name, $value) {
    $timestamp = date('Y-m-d H:i:s');
    error_log("METRIC: {$timestamp} - {$metric_name}: {$value}");
}
```

#### **Database Monitoring**
```sql
-- MySQL performance monitoring
SHOW PROCESSLIST;
SHOW ENGINE INNODB STATUS;
SELECT * FROM performance_schema.events_statements_summary_by_digest
ORDER BY sum_timer_wait DESC LIMIT 10;

-- Custom application metrics
SELECT 
    COUNT(*) as active_users,
    COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as daily_signups
FROM users;
```

### Advanced Monitoring Setup (Recommended)

#### **Prometheus + Grafana Stack**
```yaml
# docker-compose.monitoring.yml
services:
  prometheus:
    image: prom/prometheus
    ports:
      - "9090:9090"
    volumes:
      - ./monitoring/prometheus.yml:/etc/prometheus/prometheus.yml
      
  grafana:
    image: grafana/grafana
    ports:
      - "3000:3000"
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=admin
```

#### **Key Metrics to Monitor**
- **System Metrics:** CPU, Memory, Disk usage
- **Application Metrics:** Response time, error rate, throughput
- **Database Metrics:** Query performance, connection count
- **User Metrics:** Active users, session duration, feature usage
- **Business Metrics:** Message creation rate, unlock frequency

---

## 6. 💾 MEKANISME STORAGE DATA

### HYBRID Storage Architecture (MySQL + File System)
```
┌─────────────────────────────────────────────────────────────────────┐
│                        HYBRID STORAGE MODEL                         │
├─────────────────────────────────────────────────────────────────────┤
│  ┌─────────────────────────┐    ┌─────────────────────────────────┐  │
│  │    MySQL Database       │    │       File System + Apache     │  │
│  │   (Metadata & Data)     │◄──►│      (Binary Files + HTTP)     │  │
│  │                         │    │                                 │  │
│  │ • User accounts         │    │ • Profile images (JPG/PNG)     │  │
│  │ • Messages/Capsules     │    │ • Message attachments          │  │
│  │ • File metadata         │    │ • Uploaded media files         │  │
│  │   - file_path           │    │ • Apache serves files via HTTP │  │
│  │   - file_size           │    │ • Network accessible files     │  │
│  │   - file_type           │    │ • Security via .htaccess       │  │
│  │   - original_name       │    │                                 │  │
│  │ • Notifications         │    │ Access Pattern:                 │  │
│  │ • Sessions              │    │ http://IP:9080/uploads/file.jpg │  │
│  └─────────────────────────┘    └─────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

### Database Storage (MySQL)

#### **Database Schema Design** (Actual GAMON Implementation)
```sql
-- User Management dengan Profile Picture PATH
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    profile_picture VARCHAR(255) NULL,  -- Stores FILE PATH, not binary
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Time Capsule Messages (called 'capsules')
CREATE TABLE capsules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    mood_id INT,
    unlock_date DATETIME NOT NULL,
    is_unlocked BOOLEAN DEFAULT FALSE,
    unlocked_at DATETIME NULL,
    email_notification BOOLEAN DEFAULT TRUE,
    public_sharing BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mood_id) REFERENCES moods(id) ON DELETE SET NULL
);

-- Media Attachments dengan Metadata
CREATE TABLE capsule_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    capsule_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,      -- Generated filename
    original_name VARCHAR(255) NOT NULL, -- User's original filename
    file_type VARCHAR(100) NOT NULL,     -- MIME type (image/jpeg)
    file_size INT NOT NULL,              -- Size in bytes
    caption TEXT,                        -- Optional caption
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (capsule_id) REFERENCES capsules(id) ON DELETE CASCADE
);

-- Moods dengan Emoji & Color
CREATE TABLE moods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    emoji VARCHAR(10) NOT NULL,
    color VARCHAR(7) DEFAULT '#f25c5c'
);
```

#### **Data Persistence Strategy**
- **Docker Volume:** `db_data:/var/lib/mysql`
- **Backup Strategy:** Regular mysqldump exports
- **Replication:** Master-slave setup untuk production
- **Indexing:** Optimized queries dengan proper indexes

### File Storage System

#### **File Organization** (Network Accessible via Apache)
```
uploads/                                          # Apache DocumentRoot/uploads
├── .htaccess                                    # Security configuration
├── profiles/                                    # User profile images
│   ├── profile_123_1703123456.jpg              # Generated filename pattern
│   └── profile_456_1703123789.png              # profile_{user_id}_{timestamp}
├── [hash-based-files]                          # Message attachments
│   ├── 5e26a2abe508a8ca0c36b1a148004919.jpeg  # SHA1/MD5 hashed filenames
│   └── a8f7e6d4c2b9f8a6d5e4c3b2a1.png        # For security & uniqueness
└── temp/                                       # Temporary processing
    └── processing/

Network Access Pattern:
http://[SERVER_IP]:9080/uploads/profiles/profile_123_1703123456.jpg
http://[SERVER_IP]:9080/uploads/5e26a2abe508a8ca0c36b1a148004919.jpeg
```

#### **File Upload & Security Implementation**

**Profile Picture Upload (profile.php):**
```php
// Profile picture upload
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/profiles/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    $fileExtension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
    
    if (in_array($fileExtension, $allowedExtensions) && $_FILES['profile_picture']['size'] <= $maxFileSize) {
        // Generate secure filename: profile_{user_id}_{timestamp}.ext
        $newFileName = 'profile_' . $user['id'] . '_' . time() . '.' . $fileExtension;
        $destPath = $uploadDir . $newFileName;
        
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $destPath)) {
            // Store PATH in database, not binary data
            $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $stmt->execute([$destPath, $user['id']]);
        }
    }
}
```

**Apache Security Configuration:**
```apache
# docker/apache/000-default.conf
<Directory /var/www/html/uploads>
    AllowOverride None
    Require all granted        # Network accessible files
</Directory>
```

**File System Security (.htaccess in uploads/):**
```apache
Options -Indexes              # Prevent directory browsing
Options -ExecCGI              # Disable CGI execution
RemoveHandler .php .phtml     # Disable PHP execution
AddType text/plain .php       # Treat PHP files as plain text
```

#### **Storage Optimization & Network Access**

**Multi-Client Access Pattern:**
```
[Client A] ──HTTP──► http://192.168.0.102:9080/uploads/profile_123_1703123456.jpg
[Client B] ──HTTP──► http://192.168.0.102:9080/uploads/5e26a2abe508a8ca...jpeg
[Client C] ──HTTP──► http://192.168.0.102:9080/view-message.php?id=123
                                   │
                                   ▼
                     [MySQL Query: SELECT filename FROM capsule_media]
                                   │
                                   ▼
                     [HTML: <img src="/uploads/{filename}">]
                                   │
                                   ▼
[Client C] ◄──Image─── http://192.168.0.102:9080/uploads/{filename}
```

**Optimization Features:**
- ✅ **Network Accessible:** Files served via Apache HTTP
- ✅ **Cross-Platform:** Any device dapat akses via browser
- ✅ **Hybrid Efficiency:** Metadata di MySQL, binary di file system
- ✅ **Security:** .htaccess prevents malicious uploads execution
- ✅ **CDN Ready:** File paths mudah migrate ke cloud storage
- ✅ **Cache Strategy:** Apache dapat set HTTP cache headers

### Backup & Recovery Strategy

#### **Database Backup**
```bash
# Automated daily backup
docker-compose exec db mysqldump -u root -p capsule_db > backup_$(date +%Y%m%d).sql

# Point-in-time recovery
mysql -u root -p capsule_db < backup_20251218.sql
```

#### **File System Backup**
```bash
# Backup uploads folder
tar -czf uploads_backup_$(date +%Y%m%d).tar.gz ./uploads/

# Sync dengan cloud storage (future)
aws s3 sync ./uploads/ s3://gamon-uploads/ --exclude "temp/*"
```

---

## 7. 🏗️ STRUKTUR PROJECT (LAYER SERVICE)

### Overall Architecture Pattern
```
┌─────────────────────────────────────────────────────────────┐
│                     GAMON ARCHITECTURE                      │
├─────────────────────────────────────────────────────────────┤
│  Presentation Layer (Views)                                 │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │ • login.php    • dashboard.php    • profile.php        │ │
│  │ • register.php • create-message.php • calendar.php     │ │
│  └─────────────────────────────────────────────────────────┘ │
│                              │                              │
│  Application Layer (Controllers)                            │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │ • Auth.php          • MessageController.php            │ │
│  │ • AuthController.php • MoodController.php              │ │
│  │ • Capsule.php       • NotificationController.php       │ │
│  └─────────────────────────────────────────────────────────┘ │
│                              │                              │
│  Business Logic Layer (Models)                              │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │ • User Model        • Message Model                     │ │
│  │ • Authentication    • File Upload                       │ │
│  │ • Validation        • Business Rules                    │ │
│  └─────────────────────────────────────────────────────────┘ │
│                              │                              │
│  Data Access Layer                                          │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │ • Database Connection (PDO)                             │ │
│  │ • Query Builders                                        │ │
│  │ • Data Mappers                                          │ │
│  └─────────────────────────────────────────────────────────┘ │
│                              │                              │
│  Infrastructure Layer                                       │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │ • MySQL Database    • File System                       │ │
│  │ • Docker Containers • Network Layer                     │ │
│  │ • Apache Server     • PHP Runtime                       │ │
│  └─────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

### Detailed Layer Breakdown

#### **1. Presentation Layer (UI/Frontend)**
```
Frontend Components:
├── Static Assets
│   ├── assets/css/          # Stylesheets
│   ├── assets/js/           # JavaScript files
│   └── logo_gamon.png       # Brand assets
├── View Files (PHP Templates)
│   ├── Authentication
│   │   ├── login.php        # User login interface
│   │   └── register.php     # User registration
│   ├── Dashboard
│   │   ├── dashboard.php    # Main dashboard
│   │   ├── calendar.php     # Calendar view
│   │   └── dashboard_simple.php # Simplified view
│   ├── Messages/Capsules
│   │   ├── create-message.php    # Create new message
│   │   ├── view-message.php      # View message details
│   │   ├── capsule-detail.php    # Capsule management
│   │   └── mood-selector.php     # Mood selection
│   └── User Management
│       ├── profile.php      # User profile
│       ├── media.php        # Media management
│       └── notifications.php # Notifications
```

#### **2. Application Layer (Controllers)**
```php
// Controller Structure
namespace Controllers;

class MessageController {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
    }
    
    // Handle message creation
    public function create($request) {
        // 1. Validate input
        // 2. Process business logic
        // 3. Save to database
        // 4. Return response
    }
    
    // Handle message viewing
    public function view($messageId) {
        // 1. Check authorization
        // 2. Retrieve from database
        // 3. Format for display
        // 4. Return view data
    }
}
```

**Controller Responsibilities:**
- Handle HTTP requests/responses
- Input validation dan sanitization
- Business logic coordination
- Authentication dan authorization
- Error handling dan logging

#### **3. Business Logic Layer (Models)**
```php
// Model Structure
class MessageModel {
    private $db;
    
    // Core business rules
    public function createTimeCapsule($userId, $data) {
        // Business rule: Unlock date must be in future
        if (strtotime($data['unlock_date']) <= time()) {
            throw new Exception('Unlock date must be in the future');
        }
        
        // Business rule: Content validation
        if (strlen($data['content']) > 5000) {
            throw new Exception('Message too long');
        }
        
        return $this->save($userId, $data);
    }
    
    // Business logic for unlocking
    public function checkUnlockEligibility($messageId) {
        $message = $this->findById($messageId);
        return strtotime($message['unlock_date']) <= time();
    }
}
```

#### **4. Data Access Layer (DAL)**
```php
// Database abstraction
class Database {
    private $pdo;
    
    public function __construct() {
        $config = [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'dbname' => $_ENV['DB_NAME'] ?? 'capsule_db',
            'username' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASS'] ?? ''
        ];
        
        $this->connect($config);
    }
    
    // Generic query method
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    // Transaction support
    public function transaction(callable $callback) {
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
```

### Service Layer Implementation

#### **Configuration Management**
```
config/
├── database.php         # Database configuration
├── db.php              # Database connection
├── email.php           # Email service config
└── timezone.php        # Timezone settings
```

#### **Helper Services**
```
helpers/
└── SecurityHelper.php   # Security utilities
    ├── Input validation
    ├── CSRF protection
    ├── XSS prevention
    └── Authentication helpers
```

#### **Background Services**
```
cron/
├── unlockMessages.php      # Scheduled message unlocking
├── sendNotifications.php   # Email notifications
└── cleanup-sessions.php    # Session cleanup
```

### Dependency Management & IoC

#### **Service Container Pattern**
```php
class ServiceContainer {
    private $services = [];
    
    public function register($name, $factory) {
        $this->services[$name] = $factory;
    }
    
    public function get($name) {
        if (!isset($this->services[$name])) {
            throw new Exception("Service {$name} not found");
        }
        
        return $this->services[$name]();
    }
}

// Usage
$container = new ServiceContainer();
$container->register('db', function() {
    return new Database();
});
$container->register('auth', function() use ($container) {
    return new Auth($container->get('db'));
});
```

#### **Configuration-Based Architecture**
- **Environment Detection:** Auto-detect Docker vs local environment
- **Feature Flags:** Enable/disable features via configuration
- **Service Discovery:** Dynamic service registration
- **Dependency Injection:** Constructor injection untuk testability

---

## 🎯 KESIMPULAN

Aplikasi GAMON dibangun dengan arsitektur yang **scalable**, **maintainable**, dan **secure** menggunakan:

✅ **Modern Infrastructure:** Docker containerization  
✅ **Layered Architecture:** Clear separation of concerns  
✅ **Security Best Practices:** Input validation, prepared statements, etc.  
✅ **Team Collaboration:** Git workflow dengan proper branching strategy  
✅ **Monitoring & Observability:** Comprehensive logging dan metrics  
✅ **Data Management:** Robust storage dengan backup strategy  
✅ **Service-Oriented Design:** Modular dan reusable components  

Project ini siap untuk **production deployment** dan dapat **di-scale** sesuai kebutuhan pengguna.