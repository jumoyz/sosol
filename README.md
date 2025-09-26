# SOSOL - Collaborative Financial Platform

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

SOSOL is a collaborative financial platform for managing Sol/Sabotay (Collaborative savings or crowdsavings), loans, crowdfunding, wallets, and mobile money integration. It aims to provide a secure and user-friendly environment for community-based financial activities.

## About The Project

This platform is designed to empower communities by providing digital tools for traditional financial practices. Whether you are part of a savings group (Sol/Sabotay), looking to crowdfund a project, or in need of a micro-loan, SOSOL provides the necessary features to manage your finances collaboratively and transparently.

### Key Features

*   **Wallet Management:** Securely manage your funds with a personal digital wallet. Recharge and withdraw funds seamlessly.
*   **Collaborative Savings (Sol/Sabotay):** Create or join savings groups. Automate contributions and payouts.
*   **Loan Center:** Request loans from the community or offer loans to others.
*   **Crowdfunding:** Create and manage campaigns to raise funds for your projects.
*   **Mobile Money Integration:** Integrated with popular mobile money services like MonCash and NatCash for easy transactions.
*   **User-friendly Dashboard:** A comprehensive dashboard to view all your financial activities at a glance.
*   **Admin Panel:** A powerful admin dashboard to manage users, approvals, and platform settings.

## Built With

*   [PHP](https://www.php.net/)
*   [MySQL](https://www.mysql.com/)
*   [Bootstrap](https://getbootstrap.com/)
*   [JavaScript](https://developer.mozilla.org/en-US/docs/Web/JavaScript)

## Getting Started

To get a local copy up and running, follow these simple steps.

### Prerequisites

Make sure you have a local development environment with the following installed:
*   A web server (e.g., Apache, Nginx)
*   PHP 7.4 or higher
*   MySQL or MariaDB

### Installation

1.  **Clone the repo**
    ```sh
    git clone https://github.com/jumoyz/sosol.git
    ```
2.  **Database Setup**
    *   Create a new database in your MySQL/MariaDB server.
    *   Import the database schema from `database/sosol_schema.sql`.
    *   (Optional) Seed the database with initial data by importing `database/seed_data.sql`.

3.  **Configuration**
    *   Navigate to the project's root directory.
    *   Create a `.env` file. You can copy `.env.example` if it exists, or create it from scratch.
    *   Add the following environment variables to your `.env` file with your specific configuration:
    ```dotenv
    DB_HOST=localhost
    DB_USER=your_db_user
    DB_PASS=your_db_password
    DB_NAME=your_db_name

    APP_URL=http://localhost/SOSOL
    ```

4.  **Run the application**
    *   Place the project folder in your web server's root directory (e.g., `htdocs/` for XAMPP, `www/` for WAMP).
    *   Open your web browser and navigate to the `APP_URL` you set in your `.env` file.
  
## Mail & Notification Configuration

Add the following to your `.env` to enable outbound email using Gmail SMTP via PHPMailer:

```
MAIL_ENABLED=true
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=youraddress@gmail.com
MAIL_PASSWORD=your_app_password   # Use a Gmail App Password, not your normal password
MAIL_FROM_ADDRESS=youraddress@gmail.com
MAIL_FROM_NAME="SOSOL Platform"
```

Gmail Setup Steps:
1. Enable 2-Step Verification for the account.
2. Create an App Password (Security > App Passwords) choosing Mail / Other.
3. Paste the generated 16 character password (without spaces) as `MAIL_PASSWORD`.
4. If sending fails, inspect `logs/YYYY-MM-DD.log` for `MAIL_ERROR` entries.

Disable sending in local development:
```
MAIL_ENABLED=false
```

In-App Notifications:
`NotificationService` will attempt to insert into a `notifications` table (see suggested schema in `services/NotificationService.php`). If the table does not exist, it logs instead. This provides a forward-compatible path to build a notifications UI and later queue / dispatch logic.

## Project Structure

The project follows a modular structure to separate concerns and make it easy to maintain.

```
SOSOL/
│
├── 📁 public/                    # Publicly accessible assets
│   ├── 📁 css/
│   │   ├── bootstrap.min.css
│   │   └── style.css
│   ├── 📁 js/
│   │   ├── bootstrap.bundle.min.js
│   │   └── app.js
│   ├── 📁 images/
│   │   └── logos, icons, banners, etc.
│   └── 📁 uploads/
│       └── user-files/
│
├── 📁 includes/                # Shared PHP files
│   ├── config.php               # DB connection and App configuration
│   ├── constants.php            # App-specific constants
│   ├── header.php               # HTML head & top navbar
│   ├── flash-messages.php       # Fash Messages
│   ├── footer.php               # Footer content
│   ├── functions.php            # Reusable functions
│   ├── sidebar.php              # Sidebar content
│   └── auth.php                 # Login/session checker
│
├── 📁 views/                    # UI templates/screens
│   ├── 404.php
│   ├── campaign.php
│   ├── contact.php
│   ├── create-campaign.php
│   ├── crowdfunding.php
│   ├── dashboard.php
│   ├── forgot-password.php
│   ├── help-center.php
│   ├── home.php
│   ├── loan-center.php
│   ├── login.php
│   ├── logout.php
│   ├── my-campaigns.php
│   ├── notifications.php
│   ├── payment-methods.php
│   ├── privacy-policy.php
│   ├── profile.php
│   ├── register.php
│   ├── reset_password_request.php
│   ├── reset_password.php
│   ├── settings.php
│   ├── sol-groups.php
│   ├── sol-details.php
│   ├── sol-join.php
│   ├── terms.php
│   ├── transfer.php
│   ├── verification.php
│   └── wallet.php
│
├── 📁 actions/                   # PHP form handlers/API endpoints
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── wallet-recharge.php
│   ├── wallet-withdraw.php
│   ├── create-sol.php
│   ├── join-sol.php
│   ├── request-loan.php
│   ├── offer-loan.php
│   ├── repay-loan.php
│   ├── create-campaign.php
│   ├── donate.php
│   └── update-profile.php
│
├── 📁 admin/                     # Admin dashboard
│   ├── index.php
│   ├── users.php
│   ├── sol-approvals.php
│   ├── loans.php
│   ├── campaigns.php
│   └── settings.php
│
├── 📁 database/                  # DB setup and seed
│   ├── sosol_schema.sql
│   └── seed_data.sql
│
├── 📁 vendor/                  # Optional external libs (if needed)
│   └── moncash-sdk/             # MonCash PHP SDK
│
├── .htaccess                    # URL rewriting (optional)
├── .env                         # Environment variables
├── index.php                    # Main entry point
├── README.md
└── LICENSE
```

## Contributing

Contributions are what make the open source community such an amazing place to learn, inspire, and create. I would greatly appreciate any contributions you make.

If you have a suggestion that would improve this, please fork the repository and create a pull request. You can also simply open an issue with the tag "enhancement".
Don't forget to give the project a star! Thanks again!

1.  Fork the Project
2.  Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3.  Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4.  Push to the Branch (`git push origin feature/AmazingFeature`)
5.  Open a Pull Request

## License

Distributed under the MIT License. See `LICENSE` for more information.

