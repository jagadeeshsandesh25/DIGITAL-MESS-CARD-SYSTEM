<?php
// views/waiter/create.php

// Include the authentication check and session details
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'waiter') {
    header("Location: ../../login.php"); // Redirect to login if not authenticated as waiter
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['username'] ?? 'Waiter';

// Include database configuration
require_once '../../config/database.php'; // Adjust path as needed
$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Fetch list of users (customers) linked to active cards (waiter needs to select the customer placing the order)
$customers_with_cards = [];
try {
    // Join user and card to get users who have at least one active card
    $customer_query = "
        SELECT u.id, u.first_name, u.last_name, u.username, c.id as card_id, c.balance_credits, c.total_credits, c.c_status
        FROM user u
        JOIN card c ON u.id = c.user_id
        WHERE c.c_status = 'Active' -- Only users with active cards can place orders
        ORDER BY u.username, c.id -- Order by user, then by their card ID
    ";
    $customer_stmt = $db->prepare($customer_query);
    $customer_stmt->execute();
    $customers_with_cards = $customer_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Could not load customers with active cards. Please try again later.";
    error_log("Create Waiter Order page - Customers query error: " . $e->getMessage());
}

// Fetch list of menu items for the order selection
$menu_items = [];
try {
    $menu_query = "SELECT id, category, description, menu_type FROM menu ORDER BY category, menu_type, description";
    $menu_stmt = $db->prepare($menu_query);
    $menu_stmt->execute();
    $menu_items = $menu_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message .= " Could not load menu items. ";
    error_log("Create Waiter Order page - Menu query error: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $table_name = $_POST['table_name'] ?? null;
    $customer_id = $_POST['customer_id'] ?? null; // This will help select the card
    $card_id = $_POST['card_id'] ?? null; // The specific card used for the order
    $transaction_mode = $_POST['transaction_mode'] ?? 'Card';
    $ordered_items = $_POST['ordered_items'] ?? []; // Array of selected menu item IDs
    $quantities = $_POST['quantities'] ?? []; // Array of quantities for each item
    // Optional: Seat/Switch number if applicable
    // $t_s_w = (int)($_POST['t_s_w'] ?? 0);

    // Basic validation
    $errors = [];
    if (empty($table_name)) {
        $errors[] = "Please select a table name.";
    }
    if (empty($customer_id) || $customer_id == 0) {
        $errors[] = "Please select a customer.";
    }
    if (empty($card_id) || $card_id == 0) {
        $errors[] = "Please select a valid card for the customer.";
    }
    if (empty($ordered_items) || count($ordered_items) === 0) {
        $errors[] = "Please select at least one menu item.";
    }
    if (!in_array($transaction_mode, ['Cash', 'Card', 'UPI'])) {
        $errors[] = "Please select a valid transaction mode.";
    }
    // Optional validation for t_s_w if it's used
    // if ($t_s_w < 0) {
    //     $errors[] = "Seat/Switch number cannot be negative.";
    // }

    // Validate quantities and build the order description string
    $order_description_parts = [];
    // Note: The schema does not have an explicit 'item price' in the 'menu' table.
    // Calculating the total cost for the order or checking against the card balance is impossible without this information or another mechanism (like a separate `menu_prices` table linked by date/plan).
    // This code proceeds by inserting the *description* of the items ordered.
    if (!empty($ordered_items)) {
        foreach ($ordered_items as $index => $item_id) {
            $quantity = intval($quantities[$index] ?? 1); // Default quantity to 1 if not provided or invalid
            if ($quantity <= 0) {
                $errors[] = "Quantity for item ID $item_id must be greater than 0.";
                continue;
            }
            // Find the item in the fetched menu list to get its description for the order string
            $found_item = null;
            foreach ($menu_items as $menu_item) {
                if ($menu_item['id'] == $item_id) {
                    $found_item = $menu_item;
                    break;
                }
            }
            if ($found_item) {
                 // Note: This is a critical part missing from the schema - item price. We'll assume a placeholder cost calculation.
                 // In a real app, you'd get the price from the 'menu' table: $item_cost = $found_item['price'] * $quantity;
                 // For now, we'll just list the items and quantities.
                 $order_description_parts[] = $found_item['description'] . " (x$quantity)";
            } else {
                $errors[] = "Invalid menu item ID selected: $item_id";
            }
        }
    }

    $order_description = implode(', ', $order_description_parts);

    if (empty($errors)) {
        try {
            // Prepare SQL to insert the new order into the 'tabels' table
            // The 'time' field will default to the current timestamp.
            // The 'user_id' field in 'tabels' represents the *waiter* taking the order, which is the logged-in user.
            // The 't_qr' field might be derived from the table name (e.g., A=1, B=2...), or it's a separate QR code ID assigned to the table.
            // Let's use the ASCII value of the table name as a simple t_qr placeholder (A=65, B=66, etc.) and 0 for t_s_w if not used.
            $t_qr_placeholder = ord(strtoupper($table_name)); // Use uppercase A-J
            $t_s_w_placeholder = 0; // Placeholder, adjust if needed

            $insert_query = "
                INSERT INTO tabels (t_name, t_qr, t_s_w, time, transaction_mode, user_id, card_id, menu_ordered)
                VALUES (:t_name, :t_qr, :t_s_w, NOW(), :transaction_mode, :waiter_user_id, :card_id, :menu_ordered)
            ";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':t_name', $table_name);
            $insert_stmt->bindParam(':t_qr', $t_qr_placeholder); // Placeholder
            $insert_stmt->bindParam(':t_s_w', $t_s_w_placeholder); // Placeholder
            $insert_stmt->bindParam(':transaction_mode', $transaction_mode);
            $insert_stmt->bindParam(':waiter_user_id', $user_id); // The logged-in waiter takes the order
            $insert_stmt->bindParam(':card_id', $card_id);     // The card used for the order
            $insert_stmt->bindParam(':menu_ordered', $order_description); // The list of items ordered

            if ($insert_stmt->execute()) {
                $new_order_id = $db->lastInsertId();
                $success_message = "Order placed successfully! Order ID: $new_order_id";
                // Optionally, redirect to the waiter's order list page after successful creation, or clear the form
                // header("Location: index.php");
                // exit;
            } else {
                $error_message = "Failed to place the order. Please try again.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error. Please try again later.";
            error_log("Create Waiter Order error: " . $e->getMessage());
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}


// Prepare the specific content for this page
$content = "
    <h2>Take New Order</h2>
    <p>Select a table, customer, card, and menu items to place an order.</p>
";

if ($success_message) {
    $content .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
}
if ($error_message) {
    $content .= "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . htmlspecialchars($error_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
}

$content .= "
    <form method='post' action=''>
        <div class='row mb-3'>
            <div class='col-md-6'>
                <label for='table_name' class='form-label'>Select Table:</label>
                <select class='form-select' id='table_name' name='table_name' required>
                    <option value=''>Choose...</option>
                    <option value='A' " . (($_POST['table_name'] ?? '') === 'A' ? 'selected' : '') . ">Table A</option>
                    <option value='B' " . (($_POST['table_name'] ?? '') === 'B' ? 'selected' : '') . ">Table B</option>
                    <option value='C' " . (($_POST['table_name'] ?? '') === 'C' ? 'selected' : '') . ">Table C</option>
                    <option value='D' " . (($_POST['table_name'] ?? '') === 'D' ? 'selected' : '') . ">Table D</option>
                    <option value='E' " . (($_POST['table_name'] ?? '') === 'E' ? 'selected' : '') . ">Table E</option>
                    <option value='F' " . (($_POST['table_name'] ?? '') === 'F' ? 'selected' : '') . ">Table F</option>
                    <option value='G' " . (($_POST['table_name'] ?? '') === 'G' ? 'selected' : '') . ">Table G</option>
                    <option value='H' " . (($_POST['table_name'] ?? '') === 'H' ? 'selected' : '') . ">Table H</option>
                    <option value='I' " . (($_POST['table_name'] ?? '') === 'I' ? 'selected' : '') . ">Table I</option>
                    <option value='J' " . (($_POST['table_name'] ?? '') === 'J' ? 'selected' : '') . ">Table J</option>
                </select>
            </div>
            <div class='col-md-6'>
                <label for='transaction_mode' class='form-label'>Transaction Mode:</label>
                <select class='form-select' id='transaction_mode' name='transaction_mode' required>
                    <option value='Card' " . (($_POST['transaction_mode'] ?? 'Card') === 'Card' ? 'selected' : '') . ">Card</option>
                    <option value='Cash' " . (($_POST['transaction_mode'] ?? 'Card') === 'Cash' ? 'selected' : '') . ">Cash</option>
                    <option value='UPI' " . (($_POST['transaction_mode'] ?? 'Card') === 'UPI' ? 'selected' : '') . ">UPI</option>
                </select>
            </div>
        </div>

        <div class='row mb-3'>
            <div class='col-md-6'>
                <label for='customer_id' class='form-label'>Select Customer:</label>
                <select class='form-select' id='customer_id' name='customer_id' onchange='fetchCardsForCustomer(this.value)' required>
                    <option value='0'>Choose Customer...</option>
        ";

        // Group customers by their cards for easier selection
        $current_customer_id = null;
        foreach ($customers_with_cards as $customer) {
             // Start a new optgroup if the customer changes
             if ($customer['id'] !== $current_customer_id) {
                 if ($current_customer_id !== null) {
                     $content .= "</optgroup>"; // Close the previous optgroup
                 }
                 $current_customer_id = $customer['id'];
                 $customer_display_name = htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name'] . ' (' . $customer['username'] . ')');
                 $content .= "<optgroup label='$customer_display_name'>";
             }

             $selected_attr = ($_POST['customer_id'] ?? null) == $customer['id'] ? 'selected' : '';
             // Include the card details within the option text
             $card_details = "Card #{$customer['card_id']} (Balance: ₹" . number_format($customer['balance_credits'], 2) . ", Status: {$customer['c_status']})";
             $content .= "<option value='{$customer['id']}' data-card-id='{$customer['card_id']}' $selected_attr>$card_details</option>";
        }
        if ($current_customer_id !== null) {
             $content .= "</optgroup>"; // Close the last optgroup
        }

        $content .= "
                </select>
            </div>
            <div class='col-md-6'>
                <label for='card_id' class='form-label'>Select Card:</label>
                <select class='form-select' id='card_id' name='card_id' required>
                    <option value='0'>Choose Card...</option>
        ";

        // Populate cards based on the selected customer (from POST or fetched via JS)
        $selected_customer_id = $_POST['customer_id'] ?? null;
        if ($selected_customer_id) {
             foreach ($customers_with_cards as $customer) {
                 if ($customer['id'] == $selected_customer_id) {
                     $selected_attr = ($_POST['card_id'] ?? null) == $customer['card_id'] ? 'selected' : '';
                     $content .= "<option value='" . $customer['card_id'] . "' " . $selected_attr . ">Card ID: " . $customer['card_id'] . " (Balance: ₹" . number_format($customer['balance_credits'], 2) . ", Status: " . $customer['c_status'] . ")</option>";
                 }
             }
        }

        $content .= "
                </select>
            </div>
        </div>

        <div class='mb-3'>
            <h5>Select Menu Items:</h5>
            <div class='row'>
        ";

        if (!empty($menu_items)) {
            $current_category = '';
            foreach ($menu_items as $index => $item) {
                // Group by category
                if ($item['category'] !== $current_category) {
                    if ($current_category !== '') {
                        $content .= "</div></div>"; // Close previous category div
                    }
                    $current_category = $item['category'];
                    $content .= "<div class='col-md-12 mt-3'><h6>$current_category</h6><div class='row'>"; // Open new category div
                }

                $item_type_badge = $item['menu_type'] === 'Veg' ? 'bg-success' : 'bg-danger';
                $item_type_text = $item['menu_type'];
                $content .= "
                    <div class='col-md-6 col-lg-4 mb-2'>
                        <div class='form-check'>
                            <input class='form-check-input' type='checkbox' value='" . $item['id'] . "' id='item_" . $item['id'] . "' name='ordered_items[]' onchange='toggleQuantityInput(" . $item['id'] . ")'>
                            <label class='form-check-label' for='item_" . $item['id'] . "'>
                                " . htmlspecialchars($item['description']) . " <span class='badge " . $item_type_badge . "'>" . $item_type_text . "</span>
                            </label>
                            <input type='number' class='form-control form-control-sm mt-1 d-none' id='qty_" . $item['id'] . "' name='quantities[]' min='1' value='1' style='width: 60px; display: inline-block;'>
                        </div>
                    </div>
                ";
            }
            if ($current_category !== '') {
                 $content .= "</div></div>"; // Close the last category div
            }
        } else {
            $content .= "<p class='text-muted'>No menu items available.</p>";
        }

        $content .= "
            </div>
        </div>

        <div class='d-grid'>
            <button type='submit' class='btn btn-primary'>Place Order</button>
        </div>
    </form>

    <script>
        // Function to populate card dropdown based on selected customer
        // This uses the data-card-id attribute from the customer dropdown options
        function fetchCardsForCustomer(customerId) {
            const customerSelect = document.getElementById('customer_id');
            const cardSelect = document.getElementById('card_id');
            cardSelect.innerHTML = '<option value=\"0\">Choose Card...</option>'; // Clear existing options

            if (customerId && customerId != 0) {
                // Loop through all options in the customer dropdown
                for (let i = 0; i < customerSelect.options.length; i++) {
                    const option = customerSelect.options[i];
                    // Check if the option's value matches the selected customer ID
                    if (option.value == customerId && !option.disabled && option.parentElement.tagName.toLowerCase() !== 'optgroup') {
                         // Create a new option for the card dropdown
                         const newCardOption = document.createElement('option');
                         newCardOption.value = option.dataset.cardId; // Get card ID from data attribute
                         newCardOption.text = option.text; // Use the same text as the customer option
                         cardSelect.appendChild(newCardOption);
                    }
                }
                // Optionally, select the first card if only one exists for the customer
                if (cardSelect.options.length == 2) { // 2 because of the 'Choose Card...' option
                    cardSelect.selectedIndex = 1;
                }
            }
        }

        // Function to show/hide quantity input based on checkbox
        function toggleQuantityInput(itemId) {
            const checkbox = document.getElementById('item_' + itemId);
            const qtyInput = document.getElementById('qty_' + itemId);
            if (checkbox.checked) {
                qtyInput.classList.remove('d-none');
            } else {
                qtyInput.classList.add('d-none');
                qtyInput.value = 1; // Reset to 1 when unchecked
            }
        }

        // Initialize card dropdown based on initial customer selection (if any)
        document.addEventListener('DOMContentLoaded', function() {
             const initialCustomerId = document.getElementById('customer_id').value;
             if (initialCustomerId && initialCustomerId != 0) {
                 fetchCardsForCustomer(initialCustomerId);
             }
        });
    </script>
";

// Include the main layout template
include '../layouts/app.php'; // Adjust path as needed to point to the layout file

?>