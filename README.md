mess_management_system/                 # Root project folder
│
├── config/                            # Configuration files
│   ├── database.php                   # Database connection settings (PDO)
│   └── config.php                     # General application config (paths, constants, feature flags)
│
├── includes/                          # Reusable PHP components
│   ├── header.php                     # Common header HTML/PHP (navigation, Bootstrap CSS/JS)
│   ├── footer.php                     # Common footer HTML/PHP
│   ├── auth.php                       # Authentication functions (login check, session management, role checks)
│   └── functions.php                  # General utility functions (sanitization, redirects, CSRF, logging)
│
├── models/                            # Data Models (MVC - Model)
│   ├── User.php                       # Model for 'user' table
│   ├── Card.php                       # Model for 'card' table
│   ├── Menu.php                       # Model for 'menu' table
│   ├── Recharge.php                   # Model for 'recharge' table
│   ├── Table.php                      # Model for 'tabels' table (note the schema typo)
│   ├── Transaction.php                # Model for 'transactions' table
│   ├── Feedback.php                   # Model for 'feedback' table
│   ├── UserDetailsTabel.php           # Model for 'user_details_tabel' table (note the schema name)
│   └── WaiterOrder.php                # Model for waiter-specific order logic (linked to 'tabels')
│
├── controllers/                       # Business Logic Controllers (MVC - Controller)
│   ├── AuthController.php             # Handle login, logout, signup, password reset
│   ├── UserController.php             # Handle user-specific logic (CRUD, profile)
│   ├── CardController.php             # Handle card-specific logic (CRUD, balance updates)
│   ├── MenuController.php             # Handle menu-specific logic (CRUD)
│   ├── RechargeController.php         # Handle recharge-specific logic (CRUD, linking to transactions/cards)
│   ├── TableController.php            # Handle table-specific logic (CRUD, waiter assignments/orders)
│   ├── TransactionController.php      # Handle transaction-specific logic (CRUD, linking to users/cards/recharges)
│   ├── FeedbackController.php         # Handle feedback-specific logic (CRUD)
│   ├── UserDetailsController.php      # Handle user details logic (CRUD, password history)
│   ├── WaiterOrderController.php      # Handle waiter-specific order management (CRUD for 'tabels' records)
│   └── AdminController.php            # Handle admin-specific dashboard/logic (aggregates data)
│
├── views/                             # HTML templates and pages (MVC - View)
│   ├── layouts/                       # Reusable layout components
│   │   ├── app.php                    # Main application layout (header, footer wrapper) for authenticated users
│   │   └── guest.php                  # Layout for guest (non-logged-in) users (e.g., login/signup)
│   │
│   ├── auth/                          # Authentication related views
│   │   ├── login.php                  # Login page
│   │   ├── signup.php                 # Signup page
│   │   └── forgot_password.php        # Forgot password page
│   │
│   ├── dashboard/                     # Dashboard views
│   │   ├── index.php                  # Main dashboard (role-specific landing page)
│   │   ├── admin/                     # Admin-specific dashboard
│   │   │   ├── index.php              # Admin dashboard overview
│   │   │   ├── users.php              # Admin manage users
│   │   │   ├── cards.php              # Admin manage cards
│   │   │   ├── transactions.php       # Admin manage transactions
│   │   │   ├── reports.php            # Admin reports
│   │   │   └── ... (other admin sections)
│   │   ├── user/                      
│   │   │   ├── index.php             
│   │   │   ├── profile.php            
│   │   │   ├── my_card.php           
│   │   │   ├── order_history.php      
│   │   │   ├── change_password.php
│   │   │   ├── create.php
│   │   │   ├── edit.php
│   │   │   ├── menu.php
│   │   │   ├── order_success.php
│   │   │   ├── profile.php
│   │   │   ├── recharge.php
│   │   │   ├── menuu.php
│   │   │   ├── show_qr.php
│   │   │   └── view.php
│   │   └── waiter/                    # Waiter-specific dashboard
│   │       ├── index.php              # Waiter dashboard overview
│   │       ├── assigned_tables.php    # Waiter view assigned tables/orders
│   │       ├── take_order.php         # Waiter take/manage new order
│   │       └── ... (other waiter sections)
│   │
│   ├── users/                         # User management views (admin only)
│   │   ├── index.php                  # List all users
│   │   ├── create.php                 # Create new user
│   │   ├── edit.php                   # Edit existing user
│   │   ├── view.php                   # View user details
│   │   └── delete.php                 # Delete user (confirmation)
│   │
│   ├── cards/                         # Card management views
│   │   ├── index.php                  # List all cards
│   │   ├── create.php                 # Create new card
│   │   ├── edit.php                   # Edit existing card
│   │   ├── view.php                   # View card details
│   │   └── delete.php                 # Delete card (confirmation)
│   │
│   ├── menu/                          # Menu management views
│   │   ├── index.php                  # List all menu items
│   │   ├── create.php                 # Create new menu item
│   │   ├── edit.php                   # Edit existing menu item
│   │   ├── view.php                   # View menu item details
│   │   └── delete.php                 # Delete menu item (confirmation)
│   │
│   ├── recharge/                      # Recharge management views
│   │   ├── index.php                  # List all recharges
│   │   ├── create.php                 # Create new recharge
│   │   ├── edit.php                   # Edit existing recharge
│   │   ├── view.php                   # View recharge details
│   │   └── delete.php                 # Delete recharge (confirmation)
│   │
│   ├── tables/                        # Table management views ('tabels' records)
│   │   ├── index.php                  # List all table records/orders
│   │   ├── create.php                 # Create new table record/order
│   │   ├── edit.php                   # Edit existing table record/order
│   │   ├── view.php                   # View table record/order details
│   │   └── delete.php                 # Delete table record/order (confirmation)
│   │
│   ├── transactions/                  # Transaction management views
│   │   ├── index.php                  # List all transactions
│   │   ├── create.php                 # Create new transaction
│   │   ├── edit.php                   # Edit existing transaction
│   │   ├── view.php                   # View transaction details
│   │   └── delete.php                 # Delete transaction (confirmation)
│   │
│   ├── feedback/                      # Feedback management views
│   │   ├── index.php                  # List all feedback
│   │   ├── create.php                 # Create new feedback
│   │   ├── edit.php                   # Edit existing feedback
│   │   ├── view.php                   # View feedback details
│   │   └── delete.php                 # Delete feedback (confirmation)
│   │
│   ├── user_details/                  # User details views
│   │   ├── index.php                  # List all user details records
│   │   ├── create.php                 # Create new record (unusual)
│   │   ├── edit.php                   # Edit existing record
│   │   ├── view.php                   # View record details
│   │   └── delete.php                 # Delete record (confirmation)
│   │
│   └── waiter/                        # Waiter-specific views (related to 'tabels')
│       ├── index.php                  # List waiter's assigned tables/orders
│       ├── create.php                 # Take new order (links to 'tabels' create)
│       ├── edit.php                   # Update order status (links to 'tabels' edit)
│       ├── view.php                   # View order details (links to 'tabels' view)
│       └── delete.php                 # Delete order (confirmation, links to 'tabels' delete)
│
├── assets/                            # Static assets (CSS, JS, Images)
│   ├── css/
│   │   ├── style.css                  # Main stylesheet
│   │   ├── dashboard.css              # Dashboard-specific styles
│   │   └── auth.css                   # Login/signup specific styles
│   ├── js/
│   │   ├── main.js                    # Main JavaScript file (CSRF, generic validation, confirmations)
│   │   ├── auth.js                    # Authentication-specific JS
│   │   ├── dashboard.js               # Dashboard-specific JS
│   │   └── validation.js              # Form validation JS
│   └── images/
│       ├── logo.png                   # Main logo
│       └── icons/
│           ├── user-icon.png          # User icon
│           └── ...                    # Other icons
│
├── api/                               # API endpoints (for AJAX, SPAs, or external services)
│   ├── auth/
│   │   ├── login.php
│   │   ├── signup.php
│   │   └── logout.php
│   ├── users/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── update.php
│   │   └── delete.php
│   ├── cards/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── update.php
│   │   └── delete.php
│   ├── menu/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── update.php
│   │   └── delete.php
│   ├── recharge/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── update.php
│   │   └── delete.php
│   ├── tables/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── update.php
│   │   └── delete.php
│   ├── transactions/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── update.php
│   │   └── delete.php
│   ├── feedback/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── update.php
│   │   └── delete.php
│   ├── user_details/
│   │   ├── index.php
│   │   ├── create.php
│   │   ├── update.php
│   │   └── delete.php
│   └── waiter/
│       ├── index.php
│       ├── create.php
│       ├── update.php
│       └── delete.php
│
├── uploads/                           # For storing uploaded files (if needed)
│   ├── qr_codes/                      # Generated QR codes for tables
│   └── profiles/                      # User profile pictures
│
├── logs/                              # Application log files
│   └── app.log                        # Main application log
│
├── .htaccess                          # Apache rewrite rules (for clean URLs, security)
├── index.php                          # Main entry point (redirects to dashboard/login)
├── login.php                          # Login page entry point (routes to AuthController)
├── logout.php                         # Logout handler (routes to AuthController)
├── README.md                          # Project documentation
└── composer.json                      # PHP dependency management file (autoloading, packages)
# mess
