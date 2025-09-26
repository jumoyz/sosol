<?php
/**
 * Application Constants
 * 
 * This file contains all application-wide constants
 */

// Application Paths
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('VIEWS_PATH', ROOT_PATH . '/views');
define('ACTIONS_PATH', ROOT_PATH . '/actions');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('UPLOADS_PATH', PUBLIC_PATH . '/uploads');

// User Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_USER', 'user');
define('ROLE_MODERATOR', 'moderator');

// SoSol Group Types
define('SOL_TYPE_STANDARD', 'standard');
define('SOL_TYPE_PREMIUM', 'premium');
define('SOL_TYPE_BUSINESS', 'business');

// SoSol Group Statuses
define('SOL_STATUS_PENDING', 'pending');
define('SOL_STATUS_ACTIVE', 'active');
define('SOL_STATUS_COMPLETED', 'completed');
define('SOL_STATUS_SUSPENDED', 'suspended');

// Transaction Types
define('TRANSACTION_DEPOSIT', 'deposit');
define('TRANSACTION_WITHDRAWAL', 'withdrawal');
define('TRANSACTION_LOAN_PAYMENT', 'loan_payment');
define('TRANSACTION_LOAN_DISBURSEMENT', 'loan_disbursement');
define('TRANSACTION_SOL_CONTRIBUTION', 'sol_contribution');
define('TRANSACTION_SOL_PAYOUT', 'sol_payout');
define('TRANSACTION_DONATION', 'donation');

// Loan Statuses
define('LOAN_STATUS_PENDING', 'pending');
define('LOAN_STATUS_APPROVED', 'approved');
define('LOAN_STATUS_DISBURSED', 'disbursed');
define('LOAN_STATUS_REPAYING', 'repaying');
define('LOAN_STATUS_PAID', 'paid');
define('LOAN_STATUS_DEFAULTED', 'defaulted');
define('LOAN_STATUS_REJECTED', 'rejected');

// Campaign Statuses
define('CAMPAIGN_STATUS_DRAFT', 'draft');
define('CAMPAIGN_STATUS_ACTIVE', 'active');
define('CAMPAIGN_STATUS_FUNDED', 'funded');
define('CAMPAIGN_STATUS_COMPLETED', 'completed');
define('CAMPAIGN_STATUS_CANCELLED', 'cancelled');

// Time Constants
define('ONE_DAY', 86400); // 24 hours in seconds
define('ONE_WEEK', 604800); // 7 days in seconds
define('ONE_MONTH', 2592000); // 30 days in seconds

// Error Codes
define('ERROR_AUTHENTICATION', 100);
define('ERROR_AUTHORIZATION', 101);
define('ERROR_VALIDATION', 102);
define('ERROR_DATABASE', 103);
define('ERROR_PAYMENT', 104);
define('ERROR_FILE_UPLOAD', 105);