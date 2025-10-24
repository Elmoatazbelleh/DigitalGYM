# Installation Guide - Digital GYM

## Quick Start Guide

Follow these steps to set up the Digital GYM application on your local server.

### Step 1: Requirements

Ensure you have the following installed:
- PHP 7.4 or higher (tested with PHP 8.3)
- MySQL 5.7 or higher
- Apache or Nginx web server
- phpMyAdmin (optional, for easier database management)

### Step 2: Download and Extract

1. Clone or download the repository:
   ```bash
   git clone https://github.com/YOUR_USERNAME/DigitalGYM.git
   ```

2. Move the folder to your web server directory:
   - For XAMPP: `C:\xampp\htdocs\DigitalGYM`
   - For WAMP: `C:\wamp64\www\DigitalGYM`
   - For MAMP: `/Applications/MAMP/htdocs/DigitalGYM`
   - For Linux: `/var/www/html/DigitalGYM`

### Step 3: Create Database

1. Open phpMyAdmin or MySQL command line

2. Import the database:
   - **Using phpMyAdmin:**
     - Click on "New" to create a database
     - Name it `digital_gym`
     - Click on "Import" tab
     - Choose `database.sql` file
     - Click "Go"

   - **Using MySQL command line:**
     ```bash
     mysql -u root -p
     CREATE DATABASE digital_gym;
     USE digital_gym;
     SOURCE /path/to/database.sql;
     ```

### Step 4: Configure Database Connection

1. Open `includes/config.php`

2. Update the database credentials:
   ```php
   define('DB_HOST', 'localhost');      // Usually 'localhost'
   define('DB_USER', 'root');           // Your MySQL username
   define('DB_PASS', '');               // Your MySQL password
   define('DB_NAME', 'digital_gym');    // Database name
   ```

### Step 5: Set Permissions

Ensure the `uploads` folder has write permissions:

**On Linux/Mac:**
```bash
chmod 755 uploads/
```

**On Windows:**
- Right-click on the `uploads` folder
- Properties → Security → Edit
- Give "Full control" to the appropriate user

### Step 6: Access the Application

1. Start your web server (Apache/Nginx)

2. Start MySQL server

3. Open your web browser and navigate to:
   ```
   http://localhost/DigitalGYM
   ```

4. You should see the login page

### Step 7: Login

Use the default admin credentials:
- **Username:** admin
- **Password:** admin123

**Important:** Change this password immediately after first login!

## Troubleshooting

### Database Connection Error
- Verify MySQL is running
- Check database credentials in `includes/config.php`
- Ensure database `digital_gym` exists

### Page Not Found (404)
- Verify the application is in the correct web server directory
- Check that Apache/Nginx is running
- Clear browser cache

### Permission Denied for Uploads
- Check folder permissions for `uploads/` directory
- Ensure web server user has write access

### Blank Page or PHP Errors
- Check PHP error logs
- Verify PHP version is 7.4 or higher
- Enable error display in PHP (for development only):
  ```php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
  ```

## Post-Installation Steps

1. **Change Admin Password:**
   - Go to the database
   - Update the password in the `users` table
   - Use PHP's password_hash() function for new password

2. **Configure Application:**
   - Update `SITE_NAME` in `includes/config.php`
   - Update `BASE_URL` if not using localhost

3. **Add Membership Plans:**
   - The default plans are already added
   - You can modify them directly in the database

4. **Start Adding Members:**
   - Navigate to Members page
   - Click "Add New Member"
   - Fill in the details

## Security Recommendations for Production

1. **Change Database Credentials:**
   - Use a strong password for MySQL
   - Create a dedicated database user (not root)

2. **Enable HTTPS:**
   - Get an SSL certificate
   - Update `session.cookie_secure` to 1 in config.php

3. **Restrict File Uploads:**
   - Validate file types and sizes
   - Store uploads outside web root if possible

4. **Regular Backups:**
   - Backup database regularly
   - Backup uploaded files

5. **Update PHP:**
   - Keep PHP and MySQL updated
   - Monitor security advisories

## Support

For issues or questions:
- Check the main README.md
- Open an issue on GitHub
- Review PHP error logs

## Next Steps

After successful installation:
1. Explore the dashboard
2. Add your gym members
3. Create membership assignments
4. Start tracking attendance
5. Record payments

Enjoy managing your gym with Digital GYM! 🏋️‍♂️
