# GAMON Deployment & Testing Guide

## 1. Environment Setup
- **PHP Version**: 7.4 or higher (8.x recommended).
- **Database**: MySQL 5.7 or higher / MariaDB.
- **Web Server**: Apache or Nginx (Must support `.htaccess` or equivalent rewrites for security).

### Configuration
1. **Import Database**:
   - Run the SQL commands in `database.sql` to create tables and indexes.
   - Ensure the database user has `SELECT`, `INSERT`, `UPDATE` permissions.

2. **Connect Config**:
   - Edit `config/database.php`.
   - Update `$host`, `$db_name`, `$username`, and `$password` with your production credentials.

3. **File Permissions**:
   - Ensure the `uploads/` directory exists and is writable by the web server user (e.g., `www-data`).
   - `chmod 755 uploads/`

## 2. Cron Job Setup (Crucial)
The application relies on background tasks to unlock messages and send notifications.

Add the following lines to your crontab (`crontab -e`):

```bash
# Run every minute to check for messages that need unlocking
* * * * * /usr/bin/php /var/www/html/gamon/cron/unlockMessages.php >> /var/www/html/gamon/logs/cron_unlock.log 2>&1

# Run every 5 minutes to send pending email notifications
*/5 * * * * /usr/bin/php /var/www/html/gamon/cron/sendNotifications.php >> /var/www/html/gamon/logs/cron_email.log 2>&1
```
*Adjust paths (`/var/www/html/gamon`) to your actual installation directory.*

## 3. Testing Checklist

### A. Authentication
- [ ] Register a new user (check for successful DB insertion).
- [ ] Login with correct credentials (redirects to dashboard).
- [ ] Login with incorrect credentials (shows error).
- [ ] Logout (session destroyed, redirects to login).
- [ ] Access protected page (`dashboard.php`) without login (should redirect to login).

### B. Messaging Logic
- [ ] **Create Capsule**:
    - Select a user, write content, upload an image.
    - Set date to **Future** (e.g., tomorrow).
    - [ ] Verify message status is `locked` in DB.
    - [ ] Verify image file is saved in `uploads/`.
- [ ] **View Locked Capsule**:
    - Login as receiver.
    - [ ] Verify content is hidden ("This message is locked...").
    - [ ] Verify image is not shown.
- [ ] **Unlock Logic**:
    - **Method 1 (Cron)**: Wait for time to pass, ensure Cron runs. Status should update to `unlocked`.
    - **Method 2 (Lazy Load)**: If Cron fails, wait for time to pass and refresh `view-message.php`. It should auto-unlock.
- [ ] **Anonymous Mode**:
    - Send a message with "Anonymous" checked.
    - Receiver should see "From: Anonymous".

### C. Security
- [ ] **SQL Injection**: Try `' OR '1'='1` in login fields. (Should fail due to PDO).
- [ ] **XSS**: Try sending `<script>alert('XSS')</script>` as message content. (Should be escaped on view).
- [ ] **File Upload**: Try uploading a `.php` file instead of an image. (Should be rejected).

## 4. Troubleshooting

**Issue**: "Message not unlocking."
- **Check**: Is the server time zone correct? (`date` in terminal).
- **Check**: Did the Cron job run? Check `logs/cron_unlock.log`.
- **Fix**: Manually run `php cron/unlockMessages.php` to test.

**Issue**: "Image upload failed."
- **Check**: Does `uploads/` folder exist?
- **Check**: Permissions? (Windows: Properties -> Security; Linux: `chmod`).
- **Check**: `upload_max_filesize` in `php.ini`.
