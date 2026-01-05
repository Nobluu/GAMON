# GAMON Database Design

## 1. ERD Explanation & Relational Design

The GAMON application relies on a relational database schema designed to support user authentication, future capsule messaging, and notifications. The core entities are **Users**, **Messages**, and **Notifications**.

### Entities & Relationships

1.  **Users**
    *   This is the central entity.
    *   **Relationships**:
        *   **One-to-Many** with `messages` (as sender): A user can send multiple messages.
        *   **One-to-Many** with `messages` (as receiver): A user can receive multiple messages.
        *   **One-to-Many** with `notifications`: A user can receive multiple notifications.

2.  **Messages**
    *   Stores the content, scheduling, and status of the capsule messages.
    *   **Relationships**:
        *   **Many-to-One** with `users` (Sender): Linked via `sender_id`.
        *   **Many-to-One** with `users` (Receiver): Linked via `receiver_id`.
        *   **One-to-Many** with `notifications`: A message triggers a notification.

3.  **Notifications**
    *   Alerts users when a message is ready to be opened.
    *   **Relationships**:
        *   **Many-to-One** with `users`: Linked via `user_id`.
        *   **One-to-One** (or Many-to-One) with `messages`: Linked via `message_id`. Typically one unlock notification per message.

### Schema Definitions

#### 1. `users` Table
Stores user credentials and profile info.

| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | INT | PK, AUTO_INCREMENT | Unique user ID |
| `name` | VARCHAR(100) | NOT NULL | User's full name |
| `email` | VARCHAR(150) | UNIQUE, NOT NULL | User's email address |
| `password_hash` | VARCHAR(255) | NOT NULL | Hashed password |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | Account creation time |

#### 2. `messages` Table
Stores the capsule messages.

| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | INT | PK, AUTO_INCREMENT | Unique message ID |
| `sender_id` | INT | FK -> `users.id` | ID of the sender |
| `receiver_id` | INT | FK -> `users.id` | ID of the receiver |
| `content` | TEXT | NOT NULL | Message text content |
| `mood` | ENUM | 'happy', 'sad', 'nostalgic', 'hopeful' | Mood category |
| `image_path` | VARCHAR(255) | NULLABLE | Path to uploaded image |
| `open_at` | DATETIME | NOT NULL | Date/Time when message unlocks |
| `created_at` | DATETIME | DEFAULT CURRENT_TIMESTAMP | When message was created |
| `status` | ENUM | 'locked', 'unlocked', 'opened' | Current status |
| `is_anonymous` | TINYINT(1) | DEFAULT 0 | 1 = Anonymous, 0 = Normal |

#### 3. `notifications` Table
Stores scheduled or sent notifications.

| Column | Type | Constraints | Description |
| :--- | :--- | :--- | :--- |
| `id` | INT | PK, AUTO_INCREMENT | Unique notification ID |
| `user_id` | INT | FK -> `users.id` | User receiving notification |
| `message_id` | INT | FK -> `messages.id` | Related message |
| `scheduled_at` | DATETIME | NOT NULL | When to send notification |
| `sent_at` | DATETIME | NULLABLE | When it was actually sent |
| `status` | ENUM | 'pending', 'sent', 'failed' | Delivery status |

## 2. Indexing & Optimization Strategy

To ensure GAMON performs well as the dataset grows, specifically for the high-frequency reads (checking for unlocked messages), we have implemented the following indexing strategy.

### Selected Indices

#### A. `users` Table
*   **Primary Key (`id`)**: Clustered index by default. Fast access by user ID.
*   **Unique Index (`email`)**: Essential for the Login process. Ensures `SELECT * FROM users WHERE email = ?` is extremely fast (O(1) or O(log N)).

#### B. `messages` Table
*   **Foreign Keys (`sender_id`, `receiver_id`)**: MySQL automatically indexes foreign keys. This speeds up queries like "Show all messages sent by User X" or "Show inbox for User Y".
*   **Composite Index (`status`, `open_at`)**: **CRITICAL for Cron Jobs**.
    *   *Why?* The unlock script will run every minute executing:
        `SELECT * FROM messages WHERE status = 'locked' AND open_at <= NOW()`
    *   Without this index, the DB would scan every single message row. With this composite index, it jumps directly to the 'locked' subset and ranges over the dates.
*   **Single Index (`open_at`)**: Helpful for sorting messages by unlock date in the UI.

#### C. `notifications` Table
*   **Foreign Keys (`user_id`, `message_id`)**: Speed up joins.
*   **Index (`scheduled_at`)**: Used if we have a separate cron job for sending emails based on time.
*   **Index (`status`)**: Helps quickly find 'pending' notifications to retry or process.

### Query Optimization Rules
1.  **Select Specific Columns**: Avoid `SELECT *` in production where possible. Fetch only needed fields (e.g., `id`, `sender_id`, `open_at`) for lists.
2.  **Pagination**: User inboxes will use `LIMIT` and `OFFSET` to prevent loading thousands of messages at once.
3.  **Prepared Statements**: Always used (via PDO) to prevent SQL injection and allow the database to reuse query execution plans.
