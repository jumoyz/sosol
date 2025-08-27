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