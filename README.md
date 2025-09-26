SOSOL
A collaborative financial platform for managing Sol/Sabotay (Collaborative savings or crowdsavings), loans, crowdfunding, wallets, and mobile money integration (MonCash, NatCash).

SoSol-WebApp/
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
│   ├── constants.php            # App specific constants
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


There is mistakes. TODO: 
1. When a SOL Participant makes a contribution to a SOL Group, the amount should be deducted from his wallet balance if only he choose Wallet as Payment Method. And If he choose Cash as payment method, only the SOL admin/manager can confirm and approve it. 
2. SOL Payout, should not be automatic, it should manually process by the SOL admin using a "Pay" action button next with option to choose the participant preferred payout methods and deduct the admin fee.
3. Allow SOL participant to configure its preferred payout method (Wallet, Mon Cash, Nat Cash, Bank, Cash) for each SOL group participate in.
4. In SOL Settings Allow SOL Admin to define the SOL prefered payment method, prefered payout method, SOL administrative fee, and more useful settings.
5. Allow SOL admin to switch participant payout position,
6. In SOL manage page, show each participant payout method and payout position.
7. Create a public user profile page disply only information that user want to be public

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

