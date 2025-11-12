// assets/js/main.js

/**
 * --- Global JavaScript for Mess Management System ---
 *
 * This file contains general-purpose JavaScript functions and event listeners
 * that enhance the user experience and provide common functionality across the application.
 * It should be included in the main application layout (e.g., layouts/app.php).
 */

// --- Wait for the DOM to be fully loaded before executing scripts ---
document.addEventListener('DOMContentLoaded', function () {

    // --- CSRF Token Handling ---
    /**
     * Automatically adds a CSRF token to all POST forms if a meta tag exists.
     * Requires a meta tag in the HTML head like:
     * <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
     */
    (function handleCSRFToken() {
        const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfTokenMeta) {
            const csrfToken = csrfTokenMeta.getAttribute('content');
            const forms = document.querySelectorAll('form[method="post"]:not([data-no-csrf])'); // Target POST forms, exclude those with data-no-csrf attribute
            forms.forEach(form => {
                // Check if a CSRF token input already exists within the form
                if (!form.querySelector('input[name="csrf_token"]')) {
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = 'csrf_token';
                    csrfInput.value = csrfToken;
                    // Append the CSRF token input to the form
                    form.appendChild(csrfInput);
                }
            });
        } else {
            console.warn('CSRF token meta tag not found. CSRF protection might be disabled.');
        }
    })(); // IIFE (Immediately Invoked Function Expression) to encapsulate CSRF logic


    // --- Generic Form Validation ---
    /**
     * Adds basic 'is-invalid' class to empty required fields on submit for forms with class 'needs-validation'.
     * Prevents submission if validation fails.
     * Relies on Bootstrap's validation classes (.is-invalid, .was-validated).
     */
    (function handleFormValidation() {
        // Select all forms that need validation
        const forms = document.querySelectorAll('.needs-validation');
        forms.forEach(form => {
            form.addEventListener('submit', function (event) {
                // Check if the form is valid according to HTML5 validation rules
                if (!form.checkValidity()) {
                    // If the form is invalid, prevent submission
                    event.preventDefault();
                    event.stopPropagation();
                }
                // Add the 'was-validated' class to trigger Bootstrap's validation styles
                form.classList.add('was-validated');
            }, false); // Use capture phase? No, use bubbling phase (default)
        });
    })(); // IIFE to encapsulate form validation logic


    // --- Confirm Delete Actions ---
    /**
     * Adds a confirmation dialog to elements with the class 'confirm-delete'.
     * Prevents the default action if the user cancels.
     */
    (function handleConfirmDelete() {
        // Select all elements with the 'confirm-delete' class
        const deleteElements = document.querySelectorAll('.confirm-delete');
        deleteElements.forEach(element => {
            element.addEventListener('click', function (event) {
                // Get the element's text content or data attribute for a more specific message
                const itemName = this.textContent.trim() || this.dataset.itemName || 'this item';
                // Show the confirmation dialog
                if (!confirm(`Are you sure you want to delete ${itemName}? This action cannot be undone.`)) {
                    // If the user cancels, prevent the default action (navigation/form submission)
                    event.preventDefault();
                }
            });
        });
    })(); // IIFE to encapsulate delete confirmation logic


     // --- Toggle Password Visibility ---
    /**
     * Toggles the visibility of password fields.
     * Requires buttons with class 'toggle-password' and a 'data-target' attribute
     * pointing to the ID of the password input field.
     */
    (function handleTogglePasswordVisibility() {
        // Select all password toggle buttons
        const toggleButtons = document.querySelectorAll('.toggle-password');
        toggleButtons.forEach(button => {
            button.addEventListener('click', function () {
                // Get the target password field ID from the data attribute
                const targetId = this.getAttribute('data-target');
                // Find the password input field
                const passwordField = document.getElementById(targetId);
                if (passwordField) {
                    // Toggle the type attribute between 'password' and 'text'
                    const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordField.setAttribute('type', type);
                    // Toggle button text or icon (simple text example)
                    this.textContent = type === 'password' ? 'Show' : 'Hide';
                    // Example for Font Awesome icons (uncomment if using FA):
                    // const icon = this.querySelector('i');
                    // if (icon) {
                    //     icon.classList.toggle('fa-eye');
                    //     icon.classList.toggle('fa-eye-slash');
                    // }
                } else {
                    console.error(`Password field with ID '${targetId}' not found for toggle.`);
                }
            });
        });
    })(); // IIFE to encapsulate password toggle logic


    // --- Auto-hide Alerts ---
    /**
     * Automatically hides Bootstrap alerts after a specified delay (5 seconds by default).
     * Excludes alerts with the class 'alert-danger' to ensure error messages are seen.
     */
    (function handleAutoHideAlerts() {
         // Select all alerts that are not danger alerts
        const alertsToHide = document.querySelectorAll('.alert:not(.alert-danger):not([data-persistent])'); // Exclude danger alerts and those marked as persistent
        alertsToHide.forEach(alert => {
            // Set a timeout to close the alert
            setTimeout(() => {
                // Check if the alert element still exists in the DOM before trying to close it
                if (alert && alert.parentNode) {
                    // Use Bootstrap's Alert JavaScript component to close it smoothly
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 5000); // 5000 milliseconds = 5 seconds
        });
    })(); // IIFE to encapsulate auto-hide alerts logic


    // --- Dynamic Content Loading (Placeholder Example) ---
    /**
     * Example function to fetch data via AJAX.
     * This is a placeholder and requires a corresponding PHP endpoint.
     * Usage: Call this function with appropriate parameters and callbacks.
     *
     * @param {string} url - The URL of the API endpoint.
     * @param {object} data - The data to send with the request (for POST).
     * @param {function} onSuccess - Callback function on successful response.
     * @param {function} onError - Callback function on error.
     */
    window.fetchData = function(url, data = {}, onSuccess = null, onError = null) {
        const options = {
            method: 'GET', // Default to GET
            headers: {
                'Content-Type': 'application/json',
            },
        };

        // If data is provided, assume it's a POST request
        if (Object.keys(data).length > 0) {
            options.method = 'POST';
            options.body = JSON.stringify(data);
        }

        // Perform the fetch request
        fetch(url, options)
            .then(response => {
                if (!response.ok) {
                    // If the response status is not OK (200-299), throw an error
                    throw new Error(`Network response was not ok (${response.status} ${response.statusText})`);
                }
                // Parse the JSON response
                return response.json();
            })
            .then(data => {
                // If an onSuccess callback is provided, call it with the data
                if (onSuccess && typeof onSuccess === 'function') {
                    onSuccess(data);
                }
            })
            .catch(error => {
                // Log the error to the console
                console.error('Fetch error:', error);
                // If an onError callback is provided, call it with the error
                if (onError && typeof onError === 'function') {
                    onError(error);
                } else {
                    // Default error handling: Show a generic alert
                    alert('An error occurred while fetching data. Please try again.');
                }
            });
    };


    // --- Example: Fetch Cards for User (AJAX) ---
    /**
     * Fetches cards for a selected user and populates a dropdown.
     * Requires an API endpoint (e.g., api/cards/fetch_for_user.php).
     * Usage: Call this function when the user selection changes.
     *
     * @param {string|number} userId - The ID of the user.
     * @param {string} targetSelectId - The ID of the <select> element to populate.
     */
    window.fetchCardsForUser = function(userId, targetSelectId = 'card_id') {
        const cardSelect = document.getElementById(targetSelectId);
        if (!cardSelect) {
            console.error(`Target select element with ID '${targetSelectId}' not found.`);
            return;
        }

        // Clear existing options except the first placeholder
        cardSelect.innerHTML = '<option value="0">Choose Card...</option>';

        if (userId && userId != 0) {
            // Show loading state
            cardSelect.innerHTML += '<option value="0">Loading cards...</option>';
            cardSelect.disabled = true;

            // Make AJAX request using the generic fetchData function
            window.fetchData(
                BASE_URL_JS + 'api/cards/fetch_for_user.php', // Adjust path to your API endpoint
                { user_id: userId }, // Data to send
                function(data) { // Success callback
                    // Clear loading option
                    cardSelect.innerHTML = '<option value="0">Choose Card...</option>';
                    cardSelect.disabled = false;

                    // Populate with fetched cards
                    if (data && Array.isArray(data) && data.length > 0) {
                        data.forEach(card => {
                            const option = document.createElement('option');
                            option.value = card.id;
                            // Format card details for display
                            option.textContent = `Card ID: ${card.id} (Balance: ₹${parseFloat(card.balance_credits).toFixed(2)}, Total: ₹${parseFloat(card.total_credits).toFixed(2)}, Status: ${card.c_status})`;
                            cardSelect.appendChild(option);
                        });
                    } else {
                        cardSelect.innerHTML += '<option value="0">No active cards found for this user.</option>';
                    }
                },
                function(error) { // Error callback
                    console.error('Error fetching cards:', error);
                    cardSelect.innerHTML = '<option value="0">Error loading cards.</option>';
                    cardSelect.disabled = false;
                }
            );
        } else {
            // Reset if no user selected
            cardSelect.disabled = false;
        }
    };


    // --- Example: Fetch Menu Items by Category (AJAX) ---
    /**
     * Fetches menu items for a selected category and populates a list/container.
     * Requires an API endpoint (e.g., api/menu/fetch_by_category.php).
     * Usage: Call this function when the category selection changes.
     *
     * @param {string} category - The menu category (e.g., 'Breakfast', 'Lunch').
     * @param {string} targetContainerId - The ID of the container element to populate.
     */
    window.fetchMenuItemsByCategory = function(category, targetContainerId = 'menu_items_container') {
        const container = document.getElementById(targetContainerId);
        if (!container) {
            console.error(`Target container element with ID '${targetContainerId}' not found.`);
            return;
        }

        // Clear existing content
        container.innerHTML = '<p>Loading menu items...</p>';

        if (category) {
            // Make AJAX request using the generic fetchData function
            window.fetchData(
                BASE_URL_JS + 'api/menu/fetch_by_category.php', // Adjust path to your API endpoint
                { category: category }, // Data to send
                function(data) { // Success callback
                    // Clear loading message
                    container.innerHTML = '';

                    // Populate with fetched menu items
                    if (data && Array.isArray(data) && data.length > 0) {
                        const list = document.createElement('ul');
                        list.className = 'list-group';
                        data.forEach(item => {
                            const listItem = document.createElement('li');
                            listItem.className = 'list-group-item d-flex justify-content-between align-items-center';
                            listItem.textContent = item.description;

                            const badge = document.createElement('span');
                            badge.className = `badge ${item.menu_type === 'Veg' ? 'bg-success' : 'bg-danger'} rounded-pill`;
                            badge.textContent = item.menu_type;
                            listItem.appendChild(badge);

                            list.appendChild(listItem);
                        });
                        container.appendChild(list);
                    } else {
                        container.innerHTML = '<p>No menu items found for this category.</p>';
                    }
                },
                function(error) { // Error callback
                    console.error('Error fetching menu items:', error);
                    container.innerHTML = '<p class="text-danger">Error loading menu items.</p>';
                }
            );
        } else {
             container.innerHTML = '<p>Please select a category.</p>';
        }
    };

    // --- Add more general JavaScript functionality as needed for your application's interactivity ---

}); // End of DOMContentLoaded event listener


// --- Global Variables (Accessible in other JS files if needed) ---
// Example: Make BASE_URL available globally
// This should be defined in a script tag in header.php like:
// <script>
//     const BASE_URL_JS = "<?php echo BASE_URL; ?>";
// </script>
// Then you can use BASE_URL_JS in main.js and other JS files.

// --- Utility Functions ---

/**
 * Displays a temporary Bootstrap alert at the top-right corner of the screen.
 *
 * @param {string} message - The message to display in the alert.
 * @param {string} type - The type of alert (e.g., 'success', 'danger', 'warning', 'info'). Defaults to 'info'.
 */
function showAlert(message, type = 'info') {
    // Create a temporary alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.zIndex = '9999';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.maxWidth = '400px';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.body.appendChild(alertDiv);

    // Auto-hide after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) { // Check if it still exists
            const bsAlert = new bootstrap.Alert(alertDiv);
            bsAlert.close();
        }
    }, 5000);
}

/**
 * Formats a number as Indian Rupees (₹).
 *
 * @param {number} amount - The amount to format.
 * @param {number} decimals - Number of decimal places. Defaults to 2.
 * @returns {string} The formatted amount string (e.g., "₹1,234.56").
 */
function formatCurrency(amount, decimals = 2) {
    // Use Intl.NumberFormat for proper localization
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(amount);
}

/**
 * Truncates a string to a specified length and adds an ellipsis if needed.
 *
 * @param {string} str - The string to truncate.
 * @param {number} maxLength - The maximum length of the string.
 * @param {string} suffix - The suffix to append if truncated. Defaults to '...'.
 * @returns {string} The truncated string.
 */
function truncateString(str, maxLength, suffix = '...') {
    if (typeof str !== 'string' || str.length <= maxLength) {
        return str;
    }
    return str.substring(0, maxLength - suffix.length) + suffix;
}

// Export functions if using modules (optional, requires ES6 modules)
// export { showAlert, formatCurrency, truncateString };