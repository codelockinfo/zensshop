# CookPro E-commerce Website

A complete e-commerce solution built with PHP, AJAX, and Tailwind CSS, featuring a beautiful jewelry store frontend and comprehensive admin dashboard.

## Features

### Frontend

- **Responsive Landing Page** - Modern, elegant design inspired by CookPro jewelry store
- **Progressive Section Loading** - Sections load one by one via AJAX for better performance
- **Shopping Cart** - Side cart panel with AJAX functionality and cookie storage
- **Product Display** - Best selling, trending, and category-based product listings
- **Animations** - Smooth fade-in effects, hover transitions, and cart slide-in animations
- **Mobile Responsive** - Fully responsive design using Tailwind CSS

### Admin Dashboard

- **Authentication** - Login, registration, and forgot password with OTP email verification
- **Product Management** - Add, edit, delete products with image upload and retry logic
- **Order Management** - View orders, update status, track shipments
- **Category Management** - CRUD operations for product categories
- **Discount Management** - Create and manage discount codes
- **Reports** - Dashboard with statistics and analytics

### Technical Features

- **Error Handling** - Retry logic with exponential backoff (2 retries) and email notifications
- **Cookie Management** - Cart persistence using cookies (30-day expiry)
- **Session Management** - Secure session-based authentication
- **Database** - MySQL/MariaDB with prepared statements for security
- **Email Service** - PHPMailer integration for OTP and notifications

## Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL/MariaDB
- Apache/Nginx web server
- Composer (optional, for PHPMailer)

### Setup Steps

1. **Clone or extract the project** to your web server directory:

   ```
   C:\wamp64\www\oecom\
   ```

2. **Create the database**:
   - Open phpMyAdmin or MySQL command line
   - Import the schema file: `database/schema.sql`
   - This will create the database `oecom_db` with all required tables

3. **Configure database connection**:
   Edit `config/database.php`:

   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'oecom_db');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

4. **Configure email settings** (for OTP and notifications):
   Edit `config/email.php`:

   ```php
   define('SMTP_HOST', 'smtp.gmail.com');
   define('SMTP_PORT', 587);
   define('SMTP_USERNAME', 'your-email@gmail.com');
   define('SMTP_PASSWORD', 'your-app-password');
   define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
   define('ADMIN_EMAIL', 'admin@example.com');
   ```

5. **Set up PHPMailer** (optional but recommended):

   ```bash
   composer require phpmailer/phpmailer
   ```

   Or download PHPMailer manually and include it in your project.

6. **Set file permissions**:
   - Ensure `assets/images/products/` directory is writable for image uploads
   - Set appropriate permissions for uploads directory

7. **Access the website**:
   - Frontend: `http://localhost/oecom/`
   - Admin: `http://localhost/oecom/admin/`
   - Default admin credentials:
     - Email: `admin@CookPro.com`
     - Password: `admin123`

## Project Structure

```
oecom/
├── config/              # Configuration files
├── includes/            # Header and footer templates
├── assets/              # CSS, JS, and images
├── classes/            # PHP core classes
├── sections/           # Landing page sections (AJAX loaded)
├── api/                # Frontend API endpoints
├── admin/              # Admin dashboard
│   ├── api/            # Admin API endpoints
│   ├── products/       # Product management
│   ├── orders/         # Order management
│   ├── categories/     # Category management
│   └── discounts/      # Discount management
├── database/           # Database schema
└── index.php           # Landing page
```

## Usage

### Adding Products

1. Login to admin dashboard
2. Navigate to Ecommerce > Add Product
3. Fill in product details
4. Upload product images
5. Save the product

### Managing Orders

1. Go to Order > Order List
2. View order details
3. Update order status
4. Add tracking numbers

### Cart Functionality

- Products are stored in cookies (30-day expiry)
- Cart syncs with database if user is logged in
- Side cart panel opens on "Add to Cart" click
- Cart persists across page reloads

### Error Handling

- Failed operations (e.g., add product) retry up to 2 times
- After 2 failed retries, email notification sent to admin
- All errors are logged for debugging

## Customization

### Colors and Fonts

Edit `assets/css/main1.css` to modify CSS variables:

```css
:root {
  --color-primary: #1a5d3a;
  --font-heading: "Playfair Display", serif;
  --font-body: "Inter", sans-serif;
}
```

### Site Configuration

Edit `config/constants.php`:

```php
define('SITE_NAME', 'CookPro');
define('SITE_URL', 'http://localhost/oecom');
```

## Security Notes

- All database queries use prepared statements
- Passwords are hashed using `password_hash()`
- Session-based authentication
- CSRF protection recommended for production
- Input validation on all forms
- File upload restrictions recommended

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## License

This project is open source and available for use.

## Support

For issues or questions, please check the code comments or contact the development team.
