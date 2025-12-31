# Boarding House Management System

A PHP-based web application for managing boarding house bookings, user interactions, and administrative tasks. The system supports two main user roles: owners (who manage properties) and students (who book accommodations).

## Features

- **User Authentication**: Secure login and registration system with role-based access (owner/student).
- **Dashboard Management**: Separate dashboards for owners and students with tailored functionalities.
- **Booking System**: Students can view available posts, book accommodations, and manage their bookings.
- **Messaging System**: Built-in inbox and conversation features for communication between owners and students.
- **Post Management**: Owners can create, edit, and delete property posts; students can view and interact with posts.
- **Payment Integration**: Support for card payments and booking payments.
- **AJAX Functionality**: Dynamic updates for inbox and other interactive elements.
- **Mobile Integration**: Includes React Native dependencies for potential mobile app development.

## Prerequisites

- **Web Server**: Apache (recommended with XAMPP for local development)
- **PHP**: Version 7.4 or higher
- **MySQL**: Version 5.7 or higher
- **Composer**: For dependency management (if needed)
- **Node.js**: For managing JavaScript dependencies (if applicable)

## Installation

1. **Clone or Download the Project**:
   - Place the project files in your web server's root directory (e.g., `c:/xampp/htdocs/BH`).

2. **Database Setup**:
   - Create a MySQL database named `bh_system`.
   - Import the database schema from `seed.php` or manually create tables as per the application requirements.
   - Update database credentials in `db.php` if necessary.

3. **Dependencies**:
   - Ensure Bootstrap CSS and JS are available in the `css/` and `js/` directories.
   - If using React Native components, install dependencies via npm:
     ```
     npm install
     ```

4. **Configuration**:
   - Update `db.php` with your database credentials:
     ```php
     $DB_HOST = 'localhost';
     $DB_USER = 'root';
     $DB_PASS = ''; // Set your password
     $DB_NAME = 'bh_system';
     ```

5. **Run the Application**:
   - Start your web server (e.g., Apache via XAMPP).
   - Access the application at `http://localhost/BH/login.php`.

## Usage

- **Registration**: New users can register via `register.php`.
- **Login**: Existing users log in via `login.php`, which redirects to role-specific dashboards.
- **Owner Dashboard** (`owner-dashboard.php`): Manage posts, view bookings, handle inbox.
- **Student Dashboard** (`student-dashboard.php`): View posts, make bookings, manage conversations.
- **Booking Management**: Use `booking.php`, `pay_booking.php`, etc., for booking-related operations.
- **Messaging**: Access inbox via `owner_inbox.php` or `student_inbox.php`, and conversations via respective files.

## Database Setup

The application uses a MySQL database named `bh_system`. Key tables likely include:
- `users`: Stores user information (id, name, email, password, role).
- `posts`: Property listings by owners.
- `bookings`: Booking records.
- `messages`: Inbox and conversation data.

Run `seed.php` to populate initial data or set up tables manually based on the PHP scripts.

## Contributing

1. Fork the repository.
2. Create a feature branch (`git checkout -b feature/new-feature`).
3. Commit your changes (`git commit -am 'Add new feature'`).
4. Push to the branch (`git push origin feature/new-feature`).
5. Create a Pull Request.

## License

This project is open-source. Please refer to the license file if available.

## Support

For issues or questions, please check the code comments or contact the development team.
