// assets/js/dashboard.js

/**
 * --- JavaScript for Dashboard Pages (Admin/User/Waiter) ---
 *
 * This file contains specific JavaScript functions and event listeners
 * that enhance the user experience on dashboard pages.
 * It should be included in the dashboard layout (e.g., layouts/app.php) or directly on dashboard pages.
 */

// --- Wait for the DOM to be fully loaded before executing scripts ---
document.addEventListener('DOMContentLoaded', function () {

    // --- Dynamic Welcome Message ---
    /**
     * Updates the welcome message with the user's name and role.
     * Requires an element with ID 'welcome-message-placeholder' in the HTML.
     * The actual user name and role should be passed from PHP.
     */
    (function updateWelcomeMessage() {
        // These variables should be defined in the HTML by PHP before this script runs
        // Example in header.php or app.php layout:
        // <script>
        //     const DASHBOARD_USER_NAME_JS = "<?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?>";
        //     const DASHBOARD_USER_ROLE_JS = "<?php echo htmlspecialchars($_SESSION['role'] ?? 'Guest'); ?>";
        // </script>
        if (typeof DASHBOARD_USER_NAME_JS !== 'undefined' && typeof DASHBOARD_USER_ROLE_JS !== 'undefined') {
            const welcomePlaceholder = document.getElementById('welcome-message-placeholder');
            if (welcomePlaceholder) {
                welcomePlaceholder.textContent = `Welcome, ${DASHBOARD_USER_NAME_JS} (${DASHBOARD_USER_ROLE_JS})`;
            }
        } else {
            console.warn('Dashboard user name or role not defined in JavaScript.');
        }
    })(); // IIFE to encapsulate welcome message logic


    // --- Initialize Bootstrap Components ---
    /**
     * Ensures Bootstrap components like tooltips, popovers, and modals are initialized correctly.
     * This is especially important for dynamically added content or if Bootstrap's auto-initialization fails.
     */
    (function initializeBootstrapComponents() {
        // Initialize tooltips (elements with data-bs-toggle="tooltip")
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                // You can add custom options here
                // boundary: 'window',
                // customClass: 'my-custom-tooltip-class'
            });
        });

        // Initialize popovers (elements with data-bs-toggle="popover")
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl, {
                // You can add custom options here
                // trigger: 'hover focus',
                // customClass: 'my-custom-popover-class'
            });
        });

        // Modals are typically initialized by Bootstrap automatically when shown via data-bs-toggle="modal"
        // If you need programmatic control, you can initialize them like:
        // const myModal = new bootstrap.Modal(document.getElementById('myModal'), {
        //     keyboard: false
        // });
    })(); // IIFE to encapsulate Bootstrap initialization logic


    // --- Handle Quick Link Button Effects ---
    /**
     * Adds hover effects or animations to quick link buttons on dashboards.
     * Targets buttons with class 'quick-link-btn'.
     */
    (function handleQuickLinkButtonEffects() {
        const quickLinkButtons = document.querySelectorAll('.quick-link-btn');
        quickLinkButtons.forEach(button => {
            // Add mouseenter event listener for hover effect
            button.addEventListener('mouseenter', function () {
                // Apply a slight scaling transformation for visual feedback
                this.style.transform = 'scale(1.05)';
                // Optionally, add a box-shadow or change background color
                // this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.2)';
                // this.style.backgroundColor = '#0056b3'; // Darker primary color
            });

            // Add mouseleave event listener to revert hover effect
            button.addEventListener('mouseleave', function () {
                // Revert the scaling transformation
                this.style.transform = 'scale(1)';
                // Optionally, remove the box-shadow or revert background color
                // this.style.boxShadow = '';
                // this.style.backgroundColor = ''; // Reverts to CSS-defined color
            });
        });
    })(); // IIFE to encapsulate quick link button effects logic


    // --- Auto-refresh Recent Activity Section (Placeholder Example) ---
    /**
     * Periodically fetches and updates the recent activity section on dashboards.
     * Requires a corresponding PHP endpoint (e.g., api/dashboard/get_recent_activity.php).
     * Targets the element with ID 'recent-activity-section'.
     */
    (function handleAutoRefreshRecentActivity() {
        // Select the target section element
        const recentActivitySection = document.getElementById('recent-activity-section');
        // Check if the section exists
        if (recentActivitySection) {
            /**
             * Function to update the recent activity section content via AJAX.
             */
            function updateRecentActivity() {
                // Use the generic fetchData function from main.js if available
                if (typeof window.fetchData === 'function') {
                    window.fetchData(
                        BASE_URL_JS + 'api/dashboard/get_recent_activity.php', // Adjust path to your API endpoint
                        {}, // No data to send in this GET request example
                        function(data) { // Success callback
                            // Update the section content with the fetched HTML or process JSON data
                            if (data && data.html) {
                                // If the API returns HTML, inject it directly
                                recentActivitySection.innerHTML = data.html;
                            } else if (data && Array.isArray(data.activities)) {
                                // If the API returns JSON data, process it and build HTML
                                let htmlContent = '<ul class="list-group">';
                                data.activities.forEach(activity => {
                                    htmlContent += `<li class="list-group-item">${activity.description}</li>`;
                                });
                                htmlContent += '</ul>';
                                recentActivitySection.innerHTML = htmlContent;
                            } else {
                                // Handle unexpected data format
                                console.warn('Unexpected data format from recent activity API:', data);
                                recentActivitySection.innerHTML = '<p>Error loading recent activity.</p>';
                            }
                        },
                        function(error) { // Error callback
                            // Handle error (e.g., network issue, server error)
                            console.error('Error fetching recent activity:', error);
                            recentActivitySection.innerHTML = '<p class="text-danger">Failed to load recent activity. Please try again later.</p>';
                        }
                    );
                } else {
                    // Fallback if fetchData is not available
                    console.warn('window.fetchData function not found. Cannot auto-refresh recent activity.');
                    // You could implement a direct fetch here if needed
                }
            }

            // Update immediately on page load
            updateRecentActivity();

            // Set interval to update every 30 seconds (30000 milliseconds)
            // const activityInterval = setInterval(updateRecentActivity, 30000);

            // Optional: Clear the interval when the page is unloaded to prevent memory leaks
            // window.addEventListener('beforeunload', function() {
            //     clearInterval(activityInterval);
            // });
        }
    })(); // IIFE to encapsulate auto-refresh recent activity logic


    // --- Dynamic Chart Initialization (Placeholder Example) ---
    /**
     * Initializes charts on dashboard pages using Chart.js.
     * Requires Chart.js library to be included in the layout.
     * Targets canvas elements with specific IDs (e.g., 'summaryChart').
     */
    (function initializeCharts() {
        // Check if the Chart.js library is loaded
        if (typeof Chart !== 'undefined') {
            // Example: Initialize a summary chart
            const ctx = document.getElementById('summaryChart');
            if (ctx) {
                // Example Chart.js initialization (requires Chart.js library)
                // Note: You would typically fetch chart data via AJAX from a PHP endpoint
                // For demonstration, using static data
                const summaryChart = new Chart(ctx, {
                    type: 'bar', // or 'line', 'pie', 'doughnut', etc.
                    data: {
                        // These labels and data should ideally come from PHP or an API
                        labels: ['Users', 'Active Cards', 'Recharges', 'Transactions'], // Example labels
                        datasets: [{
                            label: 'Counts', // Example dataset label
                            // Example data - replace with dynamic data from PHP/JSON
                            data: [12, 19, 3, 5],
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.2)', // Red
                                'rgba(54, 162, 235, 0.2)', // Blue
                                'rgba(255, 206, 86, 0.2)', // Yellow
                                'rgba(75, 192, 192, 0.2)'  // Teal
                            ],
                            borderColor: [
                                'rgba(255, 99, 132, 1)',
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 206, 86, 1)',
                                'rgba(75, 192, 192, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            } else {
                console.warn('Canvas element with ID "summaryChart" not found for chart initialization.');
            }

            // Add more chart initializations here as needed for other dashboard sections
            // Example: Initialize a revenue chart
            // const revenueCtx = document.getElementById('revenueChart');
            // if (revenueCtx) {
            //     // ... Chart.js code for revenue chart ...
            // }

        } else {
            console.warn('Chart.js library not found. Skipping chart initialization.');
        }
    })(); // IIFE to encapsulate chart initialization logic


    // --- Handle Collapsible Sections (Accordion-like behavior) ---
    /**
     * Adds collapsible behavior to sections on dashboards.
     * Targets elements with class 'collapsible-section' and a data-target attribute.
     */
    (function handleCollapsibleSections() {
        const collapsibleHeaders = document.querySelectorAll('.collapsible-section .section-header');
        collapsibleHeaders.forEach(header => {
            header.style.cursor = 'pointer'; // Change cursor to indicate clickable
            header.addEventListener('click', function () {
                // Find the target content section
                const targetId = this.getAttribute('data-target');
                const contentSection = document.getElementById(targetId);
                if (contentSection) {
                    // Toggle the 'd-none' class to show/hide the content
                    contentSection.classList.toggle('d-none');
                    // Optionally, toggle an icon (e.g., caret) to indicate state
                    const icon = this.querySelector('.toggle-icon');
                    if (icon) {
                        icon.classList.toggle('fa-chevron-down');
                        icon.classList.toggle('fa-chevron-up');
                    }
                } else {
                    console.error(`Target content section with ID '${targetId}' not found for collapsible section.`);
                }
            });
        });
    })(); // IIFE to encapsulate collapsible sections logic


    // --- Notification Bell Animation (Placeholder Example) ---
    /**
     * Adds a subtle animation to the notification bell icon when new notifications arrive.
     * Requires an element with ID 'notification-bell' and class 'bell-icon'.
     * This is a placeholder - actual notification logic would involve polling an API or WebSockets.
     */
    (function animateNotificationBell() {
        const bellIcon = document.getElementById('notification-bell');
        if (bellIcon) {
            // Simulate a new notification arriving every 2 minutes (120000 milliseconds)
            // const notificationInterval = setInterval(() => {
            //     // Add animation class
            //     bellIcon.classList.add('animate__animated', 'animate__shakeX'); // Requires Animate.css
            //     // Remove animation class after it finishes
            //     setTimeout(() => {
            //         bellIcon.classList.remove('animate__animated', 'animate__shakeX');
            //     }, 1000); // Duration should match the Animate.css animation duration
            // }, 120000);

            // Optional: Clear the interval when the page is unloaded
            // window.addEventListener('beforeunload', function() {
            //     clearInterval(notificationInterval);
            // });
        }
    })(); // IIFE to encapsulate notification bell animation logic


    // --- Theme Switcher (Dark Mode Toggle) (Placeholder Example) ---
    /**
     * Toggles between light and dark themes for the dashboard.
     * Requires a button with ID 'theme-toggle' and a <body> element with class 'light-theme' or 'dark-theme'.
     * This is a placeholder - actual theme switching would involve loading different CSS files or manipulating CSS variables.
     */
    (function handleThemeSwitcher() {
        const themeToggleBtn = document.getElementById('theme-toggle');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function () {
                // Toggle the 'dark-theme' class on the body element
                document.body.classList.toggle('dark-theme');
                // Save the user's preference to localStorage
                const isDarkMode = document.body.classList.contains('dark-theme');
                localStorage.setItem('preferred-theme', isDarkMode ? 'dark' : 'light');
                // Update button text or icon based on theme
                this.textContent = isDarkMode ? 'Switch to Light Mode' : 'Switch to Dark Mode';
            });

            // Check for saved theme preference or respect OS preference
            const savedTheme = localStorage.getItem('preferred-theme');
            const osPrefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (savedTheme === 'dark' || (!savedTheme && osPrefersDark)) {
                document.body.classList.add('dark-theme');
                themeToggleBtn.textContent = 'Switch to Light Mode';
            } else {
                document.body.classList.remove('dark-theme');
                themeToggleBtn.textContent = 'Switch to Dark Mode';
            }
        }
    })(); // IIFE to encapsulate theme switcher logic


    // --- Add more dashboard-specific JavaScript functionality as needed ---

}); // End of DOMContentLoaded event listener