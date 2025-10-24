# 🏋️‍♂️ Digital GYM - Gym Management System

A comprehensive PHP web application for managing gym members, memberships, payments, and attendance. Built with PHP, MySQL, HTML, CSS, and JavaScript for an efficient and intuitive gym management experience.

## Features

- **Dashboard**: Real-time statistics and overview of gym operations
- **Member Management**: Easily register, update, and manage gym members with complete profile information
- **Membership Plans**: Manage different membership plans with various durations and pricing
- **Payment Tracking**: Record and track all payments with multiple payment methods
- **Attendance System**: Quick check-in/check-out system with time tracking
- **User Authentication**: Secure login system with session management
- **Responsive Design**: Mobile-friendly interface that works on all devices

## Technologies Used

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (ES6)
- **Design**: Custom CSS with modern gradient styling

## Installation

### Prerequisites

- PHP 7.4 or higher (tested with PHP 8.3)
- MySQL 5.7 or higher
- Apache/Nginx web server
- phpMyAdmin (optional, for database management)

### Setup Instructions

1. **Clone the repository**
   ```bash
   git clone https://github.com/YOUR_USERNAME/DigitalGYM.git
   cd DigitalGYM
   ```

2. **Create the database**
   - Import the `database.sql` file into your MySQL server
   - You can use phpMyAdmin or MySQL command line:
   ```bash
   mysql -u root -p < database.sql
   ```

3. **Configure database connection**
   - Edit `includes/config.php` and update the database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'digital_gym');
   ```

4. **Set up file permissions**
   ```bash
   chmod 755 uploads/
   ```

5. **Access the application**
   - Open your web browser and navigate to: `http://localhost/DigitalGYM`
   - Login with default credentials:
     - Username: `admin`
     - Password: `admin123`

## Directory Structure

```
DigitalGYM/
├── css/                    # Stylesheets
│   └── style.css          # Main stylesheet
├── js/                     # JavaScript files
│   └── main.js            # Main JavaScript functions
├── includes/               # PHP includes and configuration
│   ├── config.php         # Database configuration
│   ├── functions.php      # Helper functions
│   └── get_memberships.php # API endpoint
├── pages/                  # Application pages
│   ├── members.php        # Member management
│   ├── memberships.php    # Membership management
│   ├── payments.php       # Payment tracking
│   └── attendance.php     # Attendance system
├── uploads/                # Uploaded files
├── database.sql           # Database schema
├── index.php              # Dashboard
├── login.php              # Login page
├── logout.php             # Logout script
└── README.md              # This file
```

## Usage

### Dashboard
The dashboard provides an overview of:
- Total and active members
- Monthly revenue
- Active memberships
- Today's attendance
- Expiring memberships
- Recent members and payments

### Member Management
- Add new members with complete profile information
- Update existing member details
- Manage member status (active, inactive, suspended)
- Search and filter members
- Track emergency contact information

### Membership Management
- Assign memberships to members
- Track membership start and end dates
- Automatic expiration detection
- Cancel memberships when needed
- Multiple membership plan options

### Payment Tracking
- Record payments with different methods (cash, card, bank transfer, online)
- Link payments to specific memberships
- View payment history
- Track daily, monthly, and total revenue

### Attendance System
- Quick member check-in
- Automatic check-out tracking
- View today's attendance
- Historical attendance records with date filtering
- Duration calculation

## Security Features

- Password hashing using PHP's `password_hash()`
- Session-based authentication
- SQL injection prevention with prepared statements
- XSS protection through input sanitization
- CSRF protection recommended for production

## Default Credentials

**Admin Account:**
- Username: `admin`
- Password: `admin123`

**Important:** Change the default password immediately after first login in a production environment.

## Database Schema

The application uses the following main tables:
- `users` - System users and authentication
- `members` - Gym member information
- `membership_plans` - Available membership plans
- `member_memberships` - Active memberships
- `payments` - Payment records
- `attendance` - Check-in/check-out records

## Customization

### Adding Custom Membership Plans
1. Navigate to the database
2. Insert new records into the `membership_plans` table
3. Specify plan name, duration, price, and features

### Changing Theme Colors
Edit `css/style.css` and modify the CSS variables in the `:root` selector:
```css
:root {
    --primary-color: #2c3e50;
    --secondary-color: #3498db;
    /* ... other colors */
}
```

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is open source and available under the MIT License.

## Support

For issues, questions, or contributions, please open an issue on GitHub.

## Screenshots

The application includes:
- Modern dashboard with statistics cards
- Clean table layouts for data management
- Modal forms for adding/editing records
- Responsive design that adapts to different screen sizes
- Professional gradient header design

## Future Enhancements

Potential features for future versions:
- Trainer management
- Class scheduling
- Email notifications for expiring memberships
- SMS alerts
- Advanced reporting and analytics
- Member portal
- Equipment management
- Workout tracking
- Body measurements tracking
- Multi-language support

---

**Developed with ❤️ for efficient gym management**