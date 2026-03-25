# Prolink

Prolink is a comprehensive web platform designed to connect skilled service workers with customers. It functions as a multi-role service marketplace where users can browse, book, and review a wide range of household and professional services. The system includes distinct dashboards and functionalities for users, workers, and administrators.

## Key Features

### For Users (Customers)
- **Service Discovery:** Browse a catalog of services with powerful search and filtering by keyword, category, location, and price.
- **Booking Management:** Request bookings for specific dates and times, view booking status (pending, accepted, completed, cancelled), and manage all personal bookings.
- **Payments:** Securely pay for completed services and view a history of all transactions in a personal wallet.
- **Reviews & Ratings:** Leave reviews and ratings for completed services to help other users and provide feedback to workers.
- **User Profiles:** Manage personal profile information and upload a profile picture.
- **Communication:** Directly message workers regarding bookings through an integrated chat system.
- **Notifications:** Receive real-time notifications about booking status updates and messages.

### For Workers (Service Providers)
- **Service Management:** Create, edit, and delete service listings, including details like title, description, category, location, and price.
- **Image Gallery:** Upload and manage multiple images for each service to showcase work.
- **Booking Management:** View incoming booking requests and manage them by accepting, completing, or cancelling.
- **Profile Customization:** Manage a public worker profile with a biography, skill category, hourly rate, and profile photo.
- **Feedback:** View all reviews and ratings submitted by customers.
- **Communication:** Chat directly with users who have booked their services.
- **Earnings Wallet:** Track total earnings and view a detailed history of payments received.

### For Administrators
- **Admin Dashboard:** Access a central dashboard with an overview of platform statistics, including total users, workers, services, and revenue.
- **User Management:** Manage all user and worker accounts, with the ability to edit details and reset passwords.
- **Service Management:** Oversee all services on the platform, with permissions to edit, delete, and manage their active status.
- **Booking Moderation:** View and manage every booking on the platform.
- **Content Moderation:** Manage all reviews and contact form messages submitted through the site.
- **Financial Oversight:** View a complete log of all payments processed on the platform.

## Technology Stack

- **Backend:** PHP
- **Database:** MySQL
- **Frontend:** HTML, Tailwind CSS (via CDN)

## Setup and Installation

Follow these steps to get the Prolink application running on your local machine.

1.  **Clone the Repository**
    ```sh
    git clone https://github.com/mhammadtaki123/Prolink.git
    cd Prolink
    ```

2.  **Setup Server Environment**
    -   Use a local server environment like XAMPP, WAMP, or MAMP.
    -   Place the cloned `Prolink` directory inside your server's web root (e.g., `htdocs/` for XAMPP).

3.  **Database Setup**
    -   Open your database management tool (e.g., phpMyAdmin).
    -   Create a new database named `prolink_db`.
    -   Select the `prolink_db` database and import the `prolink_db.sql` file located in the root of the repository.

4.  **Configure Application**
    -   Navigate to the `Lib/` directory and open `config.php`.
    -   Update the database credentials if they differ from the defaults:
        ```php
        $servername = "localhost";
        $username   = "root";     // Your DB username
        $password   = "";         // Your DB password
        $dbname     = "prolink_db";
        ```
    -   Ensure the `BASE_URL` constant matches your project's folder name relative to the web root. By default, it is set correctly for this project.
        ```php
        define('BASE_URL', '/Prolink');
        ```

5.  **Run the Application**
    -   Start your local server (e.g., Apache and MySQL in XAMPP).
    -   Open your web browser and navigate to `http://localhost/Prolink`.

## Directory Structure

The repository is organized into the following key directories:

```
├── Lib/              # Core configuration and helper functions.
├── admin/            # Admin-facing pages and logic.
├── assets/           # CSS stylesheets.
├── auth/             # Login and registration scripts for different roles.
├── dashboard/        # Dashboard pages for user, worker, and admin roles.
├── pages/            # Static pages like About, Contact, Terms.
├── partials/         # Reusable components (navbar, footer).
├── process/          # Backend scripts for processing form submissions.
├── uploads/          # Directory for user-uploaded files (profile pictures, service images).
├── user/             # User-facing pages and actions (booking, reviews, etc.).
└── worker/           # Worker-facing pages and actions (managing services, etc.).
