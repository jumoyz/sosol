<?php
// Set page title
$pageTitle = "Help Center";

// Initialize variables
$articles = [
    'getting-started' => [
        [
            'id' => 'create-account',
            'title' => 'How to Create an Account',
            'content' => '
                <p>Creating an account on SoSol is quick and easy:</p>
                <ol>
                    <li>Click on the "Register" button in the top navigation bar</li>
                    <li>Fill out the registration form with your personal information</li>
                    <li>Verify your email address by clicking the link sent to your inbox</li>
                    <li>Complete your profile setup</li>
                </ol>
                <p>For security reasons, we recommend using a strong password that includes a mix of uppercase and lowercase letters, numbers, and special characters.</p>
            '
        ],
        [
            'id' => 'verify-identity',
            'title' => 'Identity Verification Process',
            'content' => '
                <p>To ensure the security of our platform, we require identity verification for all users:</p>
                <ol>
                    <li>Go to your Profile page</li>
                    <li>Click on "Verify Identity" in the KYC section</li>
                    <li>Upload a clear photo of your government-issued ID</li>
                    <li>Take a selfie for verification</li>
                    <li>Submit your information for review</li>
                </ol>
                <p>The verification process typically takes 24-48 hours. You will receive an email notification once your identity has been verified.</p>
            '
        ],
        [
            'id' => 'platform-navigation',
            'title' => 'Navigating the Platform',
            'content' => '
                <p>The SoSol platform is designed to be intuitive and easy to navigate:</p>
                <ul>
                    <li>The <strong>Dashboard</strong> provides an overview of your account activity and balance</li>
                    <li>The <strong>Wallet</strong> section allows you to manage your funds and view transaction history</li>
                    <li>The <strong>Loan Center</strong> lets you request or offer loans to other users</li>
                    <li>The <strong>Crowdfunding</strong> section showcases campaigns that you can support</li>
                    <li>The <strong>Profile</strong> section allows you to manage your personal information</li>
                </ul>
                <p>You can access these sections using the main navigation menu at the top of the page.</p>
            '
        ]
    ],
    'wallet' => [
        [
            'id' => 'deposit-funds',
            'title' => 'How to Deposit Funds',
            'content' => '
                <p>To add funds to your SoSol wallet:</p>
                <ol>
                    <li>Navigate to the "Wallet" section</li>
                    <li>Click on the "Deposit" button</li>
                    <li>Select your preferred payment method</li>
                    <li>Enter the amount you wish to deposit</li>
                    <li>Follow the instructions to complete the transaction</li>
                </ol>
                <p>Deposits are typically processed immediately, but may take longer depending on your payment method.</p>
            '
        ],
        [
            'id' => 'withdraw-funds',
            'title' => 'How to Withdraw Funds',
            'content' => '
                <p>To withdraw funds from your SoSol wallet:</p>
                <ol>
                    <li>Navigate to the "Wallet" section</li>
                    <li>Click on the "Withdraw" button</li>
                    <li>Select your withdrawal method</li>
                    <li>Enter the amount you wish to withdraw</li>
                    <li>Confirm your withdrawal</li>
                </ol>
                <p>Withdrawals are typically processed within 1-3 business days, depending on your withdrawal method.</p>
            '
        ],
        [
            'id' => 'send-money',
            'title' => 'Sending Money to Other Users',
            'content' => '
                <p>To send money to another SoSol user:</p>
                <ol>
                    <li>Navigate to the "Transfer" page</li>
                    <li>Enter the recipient\'s email address</li>
                    <li>Enter the amount you wish to send</li>
                    <li>Add an optional message</li>
                    <li>Review and confirm the transaction</li>
                </ol>
                <p>Transfers between SoSol users are instant and free of charge.</p>
            '
        ]
    ],
    'loans' => [
        [
            'id' => 'request-loan',
            'title' => 'How to Request a Loan',
            'content' => '
                <p>To request a loan on SoSol:</p>
                <ol>
                    <li>Navigate to the "Loan Center" section</li>
                    <li>Click on "Request a Loan"</li>
                    <li>Fill out the loan request form with the amount, duration, and purpose</li>
                    <li>Set your preferred interest rate</li>
                    <li>Submit your request</li>
                </ol>
                <p>Your loan request will be visible to potential lenders who can then make offers.</p>
            '
        ],
        [
            'id' => 'offer-loan',
            'title' => 'How to Offer a Loan',
            'content' => '
                <p>To offer a loan to another user:</p>
                <ol>
                    <li>Navigate to the "Loan Center" section</li>
                    <li>Browse the available loan requests</li>
                    <li>Click on "Make Offer" for the request you want to fund</li>
                    <li>Review the terms and adjust if necessary</li>
                    <li>Submit your offer</li>
                </ol>
                <p>The borrower will receive a notification and can choose to accept or decline your offer.</p>
            '
        ],
        [
            'id' => 'loan-repayment',
            'title' => 'Loan Repayment Process',
            'content' => '
                <p>When it\'s time to repay a loan:</p>
                <ol>
                    <li>Navigate to the "Loan Center" section</li>
                    <li>Find your active loan in the "My Loans" tab</li>
                    <li>Click on "Repay Loan"</li>
                    <li>Select the repayment method</li>    
                    <li>Enter the amount you wish to repay</li>
                    <li>Confirm the repayment</li>
                </ol>
                <p>Repayments are processed immediately, and you will receive a confirmation email.</p>
            '
        ]
    ],
    'crowdfunding' => [
        [
            'id' => 'create-campaign',
            'title' => 'How to Create a Campaign',
            'content' => '
                <p>To create a crowdfunding campaign on SoSol:</p>
                <ol>
                    <li>Navigate to the "Crowdfunding" section</li>
                    <li>Click on "Create Campaign"</li>
                    <li>Fill out the campaign details, including title, description, and funding goal</li>
                    <li>Set your campaign duration</li>
                    <li>Submit your campaign for review</li>
                </ol>
                <p>Once approved, your campaign will be live and you can start receiving contributions.</p>
            '
        ]
    ]
];



