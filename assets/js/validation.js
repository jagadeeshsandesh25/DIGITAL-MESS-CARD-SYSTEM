// assets/js/validation.js

/**
 * --- Reusable JavaScript Form Validation Functions ---
 *
 * This file contains general-purpose JavaScript functions for validating form inputs.
 * These functions can be used across different forms in the application.
 * They should be included in the main application layout (e.g., layouts/app.php).
 */

// --- Reusable Validation Functions ---

/**
 * Validates if a string is not empty (after trimming).
 * @param {string} str The string to validate.
 * @returns {boolean} True if valid, false otherwise.
 */
function validateNotEmpty(str) {
    return typeof str === 'string' && str.trim().length > 0;
}

/**
 * Validates if a string is a valid email format.
 * @param {string} email The email string to validate.
 * @returns {boolean} True if valid, false otherwise.
 */
function validateEmail(email) {
    // Improved regex for better email validation
    const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
    return typeof email === 'string' && emailRegex.test(email);
}

/**
 * Validates if a string contains only digits and is exactly 10 characters long.
 * @param {string} phone The phone number string to validate.
 * @returns {boolean} True if valid, false otherwise.
 */
function validatePhoneNumber(phone) {
    const phoneRegex = /^\d{10}$/;
    return typeof phone === 'string' && phoneRegex.test(phone);
}

/**
 * Validates if a password meets minimum requirements (e.g., 6 characters).
 * @param {string} password The password string to validate.
 * @returns {boolean} True if valid, false otherwise.
 */
function validatePassword(password) {
    // Example: Minimum 6 characters
    return typeof password === 'string' && password.length >= 6;
}

/**
 * Validates if two password strings match.
 * @param {string} password1 The first password.
 * @param {string} password2 The second password.
 * @returns {boolean} True if they match, false otherwise.
 */
function validatePasswordsMatch(password1, password2) {
    return password1 === password2;
}

/**
 * Validates if a number is a positive integer.
 * @param {*} num The value to validate.
 * @returns {boolean} True if valid, false otherwise.
 */
function validatePositiveInteger(num) {
    const intNum = parseInt(num, 10);
    return !isNaN(intNum) && intNum > 0 && intNum === Number(num); // Ensure it's an integer and positive
}

/**
 * Validates if a number is a non-negative integer.
 * @param {*} num The value to validate.
 * @returns {boolean} True if valid, false otherwise.
 */
function validateNonNegativeInteger(num) {
    const intNum = parseInt(num, 10);
    return !isNaN(intNum) && intNum >= 0 && intNum === Number(num); // Ensure it's an integer and non-negative
}

/**
 * Validates if a date string is in YYYY-MM-DD format.
 * @param {string} dateString The date string to validate.
 * @returns {boolean} True if valid, false otherwise.
 */
function validateDate(dateString) {
    const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
    if (!dateRegex.test(dateString)) {
        return false;
    }
    const date = new Date(dateString);
    return date instanceof Date && !isNaN(date) && date.toISOString().slice(0, 10) === dateString; // Check if valid date and matches input format
}

/**
 * Validates if a datetime string is in YYYY-MM-DD HH:MM:SS format.
 * @param {string} dateTimeString The datetime string to validate.
 * @returns {boolean} True if valid, false otherwise.
 */
function validateDateTime(dateTimeString) {
    const dateTimeRegex = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/;
    if (!dateTimeRegex.test(dateTimeString)) {
        return false;
    }
    const date = new Date(dateTimeString.replace(' ', 'T')); // Convert space to 'T' for Date constructor
    return date instanceof Date && !isNaN(date) && date.toISOString().slice(0, 19).replace('T', ' ') === dateTimeString; // Check if valid datetime and matches input format
}

/**
 * Validates if a value is one of the allowed options.
 * @param {*} value The value to validate.
 * @param {Array} allowedOptions The array of allowed values.
 * @returns {boolean} True if valid, false otherwise.
 */
function validateEnum(value, allowedOptions) {
    return Array.isArray(allowedOptions) && allowedOptions.includes(value);
}

// --- Apply Validation to Forms (Advanced Example) ---

/**
 * Applies custom validation logic to a form field.
 * Adds/removes Bootstrap's 'is-invalid' class based on validation result.
 * @param {HTMLElement} field The form field element to validate.
 * @param {Function} validator A function that takes the field's value and returns true/false.
 * @param {string} errorMessage The error message to display if validation fails.
 */
function applyValidation(field, validator, errorMessage) {
    if (!field || typeof validator !== 'function') return;

    const form = field.closest('form');
    if (!form) return;

    // Validate on input/change
    const validateField = () => {
        const isValid = validator(field.value);
        if (!isValid) {
            field.classList.add('is-invalid');
            // Create or update error message
            let errorElement = field.parentNode.querySelector('.invalid-feedback-custom');
            if (!errorElement) {
                errorElement = document.createElement('div');
                errorElement.className = 'invalid-feedback invalid-feedback-custom';
                field.parentNode.appendChild(errorElement);
            }
            errorElement.textContent = errorMessage;
        } else {
            field.classList.remove('is-invalid');
            const errorElement = field.parentNode.querySelector('.invalid-feedback-custom');
            if (errorElement) {
                errorElement.remove();
            }
        }
    };

    field.addEventListener('input', validateField);
    field.addEventListener('blur', validateField);

    // Validate on form submit
    form.addEventListener('submit', function(e) {
        validateField(); // Run validation once more on submit
        if (field.classList.contains('is-invalid')) {
            e.preventDefault(); // Prevent submission if invalid
            // Scroll to the first invalid field
            if (!form.querySelector('.is-invalid')) {
                 field.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
}

// --- Example usage of applyValidation (Uncomment and adapt in your specific forms) ---
/*
document.addEventListener('DOMContentLoaded', function () {
    const emailField = document.getElementById('email');
    if (emailField) {
        applyValidation(emailField, validateEmail, 'Please enter a valid email address.');
    }

    const phoneField = document.getElementById('ph_no');
    if (phoneField) {
        applyValidation(phoneField, validatePhoneNumber, 'Phone number must be 10 digits.');
    }

    const passwordField = document.getElementById('password');
    if (passwordField) {
        applyValidation(passwordField, validatePassword, 'Password must be at least 6 characters long.');
    }

    const confirmPasswordField = document.getElementById('confirm_password');
    if (confirmPasswordField && passwordField) {
        applyValidation(confirmPasswordField, function(value) {
            return validatePasswordsMatch(value, passwordField.value);
        }, 'Passwords do not match.');
    }
});
*/

// --- Export functions if using modules (optional) ---
// export {
//     validateNotEmpty,
//     validateEmail,
//     validatePhoneNumber,
//     validatePassword,
//     validatePasswordsMatch,
//     validatePositiveInteger,
//     validateNonNegativeInteger,
//     validateDate,
//     validateDateTime,
//     validateEnum,
//     applyValidation
// };