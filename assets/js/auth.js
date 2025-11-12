// assets/js/auth.js

/**
 * --- JavaScript for Authentication Pages (Login/Signup/Forgot Password) ---
 *
 * This file contains specific JavaScript functions and event listeners
 * that enhance the user experience on authentication pages.
 * It should be included in the auth layout (e.g., layouts/guest.php) or directly on auth pages.
 */

// --- Wait for the DOM to be fully loaded before executing scripts ---
document.addEventListener('DOMContentLoaded', function () {

    // --- Toggle Password Visibility ---
    /**
     * Toggles the visibility of password fields on auth pages.
     * Requires buttons with class 'toggle-password-auth' and a 'data-target' attribute
     * pointing to the ID of the password input field.
     */
    (function handleTogglePasswordVisibilityAuth() {
        // Select all password toggle buttons specific to auth pages
        const toggleButtons = document.querySelectorAll('.toggle-password-auth');
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
                    // Toggle button text (simple text example)
                    this.textContent = type === 'password' ? 'Show' : 'Hide';
                    // Example for Font Awesome icons (uncomment if using FA):
                    // const icon = this.querySelector('i');
                    // if (icon) {
                    //     icon.classList.toggle('fa-eye');
                    //     icon.classList.toggle('fa-eye-slash');
                    // }
                } else {
                    console.error(`Password field with ID '${targetId}' not found for toggle on auth page.`);
                }
            });
        });
    })(); // IIFE to encapsulate auth password toggle logic


    // --- Client-Side Form Validation for Auth Forms ---
    /**
     * Adds enhanced client-side validation to auth forms (login, signup, forgot password).
     * Provides real-time feedback and prevents submission of invalid data.
     * Targets forms with class 'auth-form-needs-validation'.
     */
    (function handleAuthFormValidation() {
        // Select all auth forms that need enhanced validation
        const forms = document.querySelectorAll('.auth-form-needs-validation');
        forms.forEach(form => {
            form.addEventListener('submit', function (event) {
                // Prevent default submission initially
                event.preventDefault();

                let isValid = true;
                const requiredFields = form.querySelectorAll('[required]');

                // Reset previous validation states
                requiredFields.forEach(field => {
                    field.classList.remove('is-invalid');
                    // Clear any existing feedback messages for this field
                    const feedbackElement = field.parentNode.querySelector('.invalid-feedback');
                    if (feedbackElement) {
                        feedbackElement.remove();
                    }
                });

                // Check required fields
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('is-invalid');
                        // Create and append feedback message
                        const feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback';
                        feedback.textContent = 'This field is required.';
                        field.parentNode.appendChild(feedback);
                    }
                });

                // Specific validation for email fields
                const emailFields = form.querySelectorAll('input[type="email"]');
                emailFields.forEach(emailField => {
                    const emailValue = emailField.value.trim();
                    // Basic email regex (same as HTML5)
                    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
                    if (emailValue && !emailRegex.test(emailValue)) {
                        isValid = false;
                        emailField.classList.add('is-invalid');
                        // Create and append feedback message
                        const feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback';
                        feedback.textContent = 'Please enter a valid email address.';
                        emailField.parentNode.appendChild(feedback);
                    }
                });

                 // Specific validation for phone number fields (assuming 10 digits)
                const phoneFields = form.querySelectorAll('input[type="tel"]');
                phoneFields.forEach(phoneField => {
                    const phoneValue = phoneField.value.trim();
                    // Basic phone number regex (10 digits, no spaces or dashes)
                    const phoneRegex = /^\d{10}$/;
                    if (phoneValue && !phoneRegex.test(phoneValue)) {
                        isValid = false;
                        phoneField.classList.add('is-invalid');
                        // Create and append feedback message
                        const feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback';
                        feedback.textContent = 'Please enter a valid 10-digit phone number.';
                        phoneField.parentNode.appendChild(feedback);
                    }
                });

                // Specific validation for password fields (minimum 6 characters)
                const passwordFields = form.querySelectorAll('input[type="password"]');
                passwordFields.forEach(passwordField => {
                    const passwordValue = passwordField.value;
                    if (passwordValue && passwordValue.length < 6) {
                        isValid = false;
                        passwordField.classList.add('is-invalid');
                        // Create and append feedback message
                        const feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback';
                        feedback.textContent = 'Password must be at least 6 characters long.';
                        passwordField.parentNode.appendChild(feedback);
                    }
                });

                // Specific validation for username fields (alphanumeric, underscore, dot, hyphen, 3-20 chars)
                const usernameFields = form.querySelectorAll('input[name="username"]');
                usernameFields.forEach(usernameField => {
                    const usernameValue = usernameField.value.trim();
                    // Basic username regex (alphanumeric, underscore, dot, hyphen, 3-20 chars)
                    const usernameRegex = /^[a-zA-Z0-9_.-]{3,20}$/;
                    if (usernameValue && !usernameRegex.test(usernameValue)) {
                        isValid = false;
                        usernameField.classList.add('is-invalid');
                        // Create and append feedback message
                        const feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback';
                        feedback.textContent = 'Username must be 3-20 characters long and can only contain letters, numbers, underscores, dots, and hyphens.';
                        usernameField.parentNode.appendChild(feedback);
                    }
                });

                // Specific validation for confirm password fields
                const confirmPasswordFields = form.querySelectorAll('input[name="confirm_password"]');
                confirmPasswordFields.forEach(confirmPasswordField => {
                    const confirmPasswordValue = confirmPasswordField.value;
                    const passwordField = form.querySelector('input[name="password"]');
                    const passwordValue = passwordField ? passwordField.value : '';
                    if (confirmPasswordValue && confirmPasswordValue !== passwordValue) {
                        isValid = false;
                        confirmPasswordField.classList.add('is-invalid');
                        // Create and append feedback message
                        const feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback';
                        feedback.textContent = 'Passwords do not match.';
                        confirmPasswordField.parentNode.appendChild(feedback);
                    }
                });

                // Specific validation for gender selection (assuming radio buttons)
                const genderGroups = form.querySelectorAll('[name="gender"]');
                if (genderGroups.length > 0) {
                    let genderSelected = false;
                    genderGroups.forEach(radio => {
                        if (radio.checked) {
                            genderSelected = true;
                        }
                    });
                    if (!genderSelected) {
                        isValid = false;
                        // Find the gender group container or the last radio button's parent
                        const genderGroupContainer = genderGroups[0].closest('.form-group') || genderGroups[genderGroups.length - 1].parentNode;
                        // Create and append feedback message
                        const feedback = document.createElement('div');
                        feedback.className = 'invalid-feedback d-block'; // Ensure it's displayed as a block
                        feedback.textContent = 'Please select your gender.';
                        genderGroupContainer.appendChild(feedback);
                    }
                }

                // Specific validation for role selection (assuming dropdown)
                 const roleSelects = form.querySelectorAll('select[name="role"]');
                 roleSelects.forEach(roleSelect => {
                     const roleValue = roleSelect.value.trim();
                     if (roleValue && !['admin', 'waiter', 'user'].includes(roleValue)) {
                         isValid = false;
                         roleSelect.classList.add('is-invalid');
                         // Create and append feedback message
                         const feedback = document.createElement('div');
                         feedback.className = 'invalid-feedback';
                         feedback.textContent = 'Please select a valid role.';
                         roleSelect.parentNode.appendChild(feedback);
                     }
                 });


                // If the form is valid, submit it
                if (isValid) {
                    // Re-enable browser's default validation and submit
                    form.classList.add('was-validated');
                    form.submit();
                } else {
                    // Add the 'was-validated' class to trigger Bootstrap's validation styles
                    form.classList.add('was-validated');
                    // Prevent submission
                    event.preventDefault();
                    event.stopPropagation();
                }
            }, false); // Use capture phase? No, use bubbling phase (default)

            // Optional: Add real-time validation on input change/blur for immediate feedback
            const inputsToValidateOnBlur = form.querySelectorAll('input, select, textarea');
            inputsToValidateOnBlur.forEach(input => {
                input.addEventListener('blur', function() {
                    // Trigger the submit handler's validation logic for this specific field
                    // This is a simplified check, you might want to call specific validation functions
                    // based on the input type or name.
                     const event = new Event('submit', { cancelable: true });
                     form.dispatchEvent(event); // Dispatch a submit event to trigger validation, but prevent actual submission in the handler if needed.
                     // A more targeted approach would be better for real-time feedback without triggering full submit logic.
                });
            });
        });
    })(); // IIFE to encapsulate auth form validation logic


    // --- Handle Forgot Password Form Submission (Optional AJAX Example) ---
    /**
     * Example function to handle forgot password form submission via AJAX.
     * Requires a corresponding PHP endpoint (e.g., api/auth/forgot_password.php).
     * This prevents a full page reload and provides a smoother user experience.
     * Targets the form with ID 'forgotPasswordForm'.
     */
    (function handleForgotPasswordAjax() {
        const forgotPasswordForm = document.getElementById('forgotPasswordForm');
        if (forgotPasswordForm) {
            forgotPasswordForm.addEventListener('submit', function(event) {
                event.preventDefault(); // Prevent default form submission

                const submitButton = forgotPasswordForm.querySelector('button[type="submit"]');
                const originalButtonText = submitButton.textContent;
                submitButton.disabled = true;
                submitButton.textContent = 'Sending...';

                const emailInput = document.getElementById('email_or_username');
                const emailValue = emailInput.value.trim();

                // Basic client-side validation before sending
                if (!emailValue) {
                    alert('Please enter your email or username.');
                    submitButton.disabled = false;
                    submitButton.textContent = originalButtonText;
                    return;
                }

                // Prepare data to send
                const data = {
                    email_or_username: emailValue
                };

                // Use the generic fetchData function from main.js if available, or implement fetch directly
                if (typeof window.fetchData === 'function') {
                    // Use the generic fetchData function
                    window.fetchData(
                        BASE_URL_JS + 'api/auth/forgot_password.php', // Adjust path to your API endpoint
                        data,
                        function(responseData) { // Success callback
                            // Handle successful response (e.g., show success message)
                            alert(responseData.message || 'If an account exists, a password reset link has been sent.');
                            // Optionally, redirect to login page
                            // window.location.href = BASE_URL_JS + 'views/auth/login.php';
                        },
                        function(error) { // Error callback
                            // Handle error response (e.g., show error message)
                            console.error('Forgot Password AJAX error:', error);
                            alert('An error occurred. Please try again later.');
                        }
                    );
                } else {
                    // Fallback: Implement fetch directly if fetchData is not available
                    console.warn('window.fetchData function not found. Using direct fetch.');
                    fetch(BASE_URL_JS + 'api/auth/forgot_password.php', { // Adjust path to your API endpoint
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(data),
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Handle successful response
                        alert(data.message || 'If an account exists, a password reset link has been sent.');
                        // Optionally, redirect to login page
                        // window.location.href = BASE_URL_JS + 'views/auth/login.php';
                    })
                    .catch(error => {
                        // Handle error response
                        console.error('Forgot Password AJAX error:', error);
                        alert('An error occurred. Please try again later.');
                    });
                }

                // Reset button state after a short delay (simulate network latency)
                setTimeout(() => {
                    submitButton.disabled = false;
                    submitButton.textContent = originalButtonText;
                }, 1000); // 1 second delay
            });
        }
    })(); // IIFE to encapsulate forgot password AJAX logic


    // --- Focus First Input Field on Auth Pages ---
    /**
     * Automatically focuses the first text, email, or password input field on auth pages for better UX.
     * Targets forms with class 'auth-form'.
     */
    (function focusFirstInputField() {
        const authForms = document.querySelectorAll('.auth-form');
        authForms.forEach(form => {
            const firstInput = form.querySelector('input[type="text"], input[type="email"], input[type="password"], input[name="username"]');
            if (firstInput) {
                firstInput.focus();
            }
        });
    })(); // IIFE to encapsulate focus logic

    // --- Add more authentication-specific JavaScript functionality as needed ---

}); // End of DOMContentLoaded event listener