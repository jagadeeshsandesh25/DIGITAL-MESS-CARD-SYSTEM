<?php
// controllers/FeedbackController.php

require_once '../config/database.php'; // Adjust path
require_once '../models/Feedback.php'; // Adjust path
require_once '../models/User.php'; // Adjust path - Needed for user context
require_once '../includes/auth.php'; // Adjust path - For permission checks

class FeedbackController {

    private $database;
    private $db;
    private $feedbackModel;
    private $userModel;

    public function __construct() {
        $this->database = new Database();
        $this->db = $this->database->getConnection();
        $this->feedbackModel = new Feedback($this->db);
        $this->userModel = new User($this->db); // Pass the same DB connection
    }

    public function index() {
        // Check if user is logged in
        requireLogin();
        $user_role = $_SESSION['role'];
        $user_id = $_SESSION['user_id'];

        $feedback_records = [];
        $error_message = '';

        try {
            // Fetch feedback records based on user role
            if ($user_role === 'admin') {
                // Admin sees all feedback
                $feedback_records = $this->feedbackModel->findAll(); // Default order by created_at DESC
            } else {
                // Regular users and waiters see only their own feedback
                $feedback_records = $this->feedbackModel->findByUserId($user_id); // Order by created_at DESC
            }
        } catch (PDOException $e) {
            $error_message = "Could not load feedback records. Please try again later.";
            error_log("FeedbackController index query error: " . $e->getMessage());
        }

        // Prepare the content for the index view using a helper
        $content = $this->getIndexContent($feedback_records, $error_message, $user_role, $user_id);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function create() {
        // Check if user is logged in (any role can give feedback)
        requireLogin();
        $user_id = $_SESSION['user_id'];

        $success_message = '';
        $error_message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $message = trim($_POST['message'] ?? '');
            $rating = $_POST['rating'] ?? null; // Rating is optional based on schema (can be NULL)

            // Basic validation
            $errors = [];
            if (empty($message)) {
                $errors[] = "Feedback message cannot be empty.";
            }
            // Validate rating if provided
            if ($rating !== null && (!is_numeric($rating) || $rating < 1 || $rating > 5)) {
                 $errors[] = "Please select a rating between 1 and 5 stars, or leave it blank.";
            }

            if (empty($errors)) {
                // Prepare data array for the model
                $new_feedback_data = [
                    'user_id' => $user_id, // The logged-in user gives the feedback
                    'message' => $message,
                    'rating' => $rating, // Can be NULL
                    'created_at' => date('Y-m-d H:i:s') // Set the current time
                ];

                // Attempt to create the feedback record using the model
                $new_feedback_id = $this->feedbackModel->create($new_feedback_data);

                if ($new_feedback_id) {
                    $success_message = "Thank you for your feedback! Feedback ID: $new_feedback_id";
                    // Optionally, redirect to the feedback list page (their own) or a thank you page
                    // header("Location: index.php?success=submitted");
                    // exit;
                } else {
                    $error_message = "Failed to submit feedback. Please try again.";
                }
            } else {
                $error_message = implode("<br>", $errors); // Join multiple errors with line breaks
            }
        }

        // Load the create feedback view
        $content = $this->getCreateContent($success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function edit() {
        // Check if user is logged in
        requireLogin();
        $user_role = $_SESSION['role'];
        $user_id = $_SESSION['user_id'];

        $feedback_id_to_edit = $_GET['id'] ?? null;
        if (!$feedback_id_to_edit) {
            header("Location: index.php"); // Redirect to list if no ID
            exit;
        }

        $feedback_details = null;
        $success_message = '';
        $error_message = '';

        // Fetch the specific feedback record's details
        try {
             $feedback_details = $this->feedbackModel->findById($feedback_id_to_edit);

             if (!$feedback_details) {
                 $error_message = "Feedback record not found.";
             } elseif ($user_role !== 'admin' && $feedback_details['user_id'] != $user_id) {
                  // Non-admins can only edit their own feedback
                  $error_message = "Access denied. You can only edit your own feedback.";
                  $feedback_details = null; // Clear details to prevent editing
             }
        } catch (PDOException $e) {
            $error_message = "Could not load feedback details. Please try again later.";
            error_log("FeedbackController edit fetch query error: " . $e->getMessage());
        }

        // Process form submission if feedback details were found and user has permission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $feedback_details) {
            $message = trim($_POST['message'] ?? '');
            $rating = $_POST['rating'] ?? null; // Rating is optional

            // Basic validation
            $errors = [];
            if (empty($message)) {
                $errors[] = "Feedback message cannot be empty.";
            }
            // Validate rating if provided
            if ($rating !== null && (!is_numeric($rating) || $rating < 1 || $rating > 5)) {
                 $errors[] = "Please select a rating between 1 and 5 stars, or leave it blank.";
            }

            if (empty($errors)) {
                // Prepare data array for the model, including the ID
                $update_feedback_data = [
                    'id' => $feedback_id_to_edit,
                    'message' => $message,
                    'rating' => $rating // Can be NULL
                ];

                // Attempt to update the feedback record using the model
                if ($this->feedbackModel->update($update_feedback_data)) {
                    $success_message = "Feedback updated successfully!";
                    // Optionally, refetch the feedback details to show updated info
                     $feedback_details = $this->feedbackModel->findById($feedback_id_to_edit); // Update the local variable
                } else {
                    $error_message = "Failed to update feedback. Please try again.";
                }
            } else {
                $error_message = implode("<br>", $errors);
            }
        }


        // Load the edit feedback view
        $content = $this->getEditContent($feedback_details, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    public function delete() {
        // Check if user is logged in
        requireLogin();
        $user_role = $_SESSION['role'];
        $user_id = $_SESSION['user_id'];

        $feedback_id_to_delete = $_GET['id'] ?? null;
        if (!$feedback_id_to_delete) {
            header("Location: index.php"); // Redirect to list if no ID
            exit;
        }

        $feedback_details = null;
        $success_message = '';
        $error_message = '';

        // Fetch the specific feedback record's details for confirmation
        try {
             $feedback_details = $this->feedbackModel->findById($feedback_id_to_delete);

             if (!$feedback_details) {
                 $error_message = "Feedback record not found.";
             } elseif ($user_role !== 'admin' && $feedback_details['user_id'] != $user_id) {
                  // Non-admins can only attempt to delete their own feedback
                  $error_message = "Access denied. You can only delete your own feedback.";
                  $feedback_details = null; // Clear details to prevent deletion
             }
        } catch (PDOException $e) {
            $error_message = "Could not load feedback details for deletion. Please try again later.";
            error_log("FeedbackController delete fetch query error: " . $e->getMessage());
        }

        // Process deletion if confirmed via POST and user has permission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $feedback_details && isset($_POST['confirm_delete'])) {
            // Attempt to delete the feedback record using the model
            // WARNING: This is likely to fail due to data retention policies or constraints.
            if ($this->feedbackModel->delete($feedback_id_to_delete)) {
                $success_message = "Feedback record deleted successfully!";
                // Optionally, redirect to the feedback list page after successful deletion
                // header("Location: index.php?success=deleted");
                // exit;
            } else {
                // This could mean the row didn't exist or a constraint prevented deletion
                $error_message = "Failed to delete feedback record. It might not exist or be linked to other records that prevent deletion (e.g., audit trail requirements).";
            }
        }


        // Load the delete feedback confirmation view
        $content = $this->getDeleteContent($feedback_details, $success_message, $error_message);
        include '../views/layouts/app.php'; // Use the authenticated layout
    }

    // --- Helper Methods to Generate View Content ---

    private function getIndexContent($feedback_records, $error_message = '', $user_role, $user_id) {
        $html = "<h2>Feedback Records</h2><p>" . ($user_role === 'admin' ? 'View all' : 'View your') . " feedback submissions.</p>";

        if ($error_message) {
            $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
        } else {
            // Add a button to submit new feedback (for all logged-in users)
            $html .= "
            <div class='mb-3'>
                <a href='FeedbackController.php?action=create' class='btn btn-success'>Submit New Feedback</a>
            </div>
            ";

            // Check if feedback records exist
            if (!empty($feedback_records)) {
                $html .= "
                <div class='table-responsive'>
                    <table class='table table-striped table-hover'>
                        <thead class='table-dark'>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Message</th>
                                <th>Rating</th>
                                <th>Submitted At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                ";

                foreach ($feedback_records as $record) {
                    $user_name_display = $record['user_first_name'] ? htmlspecialchars($record['user_first_name'] . ' ' . $record['user_last_name']) : htmlspecialchars($record['user_username']);
                    $user_link = $record['user_id'] ? "<a href='../views/users/view.php?id=" . $record['user_id'] . "'>" . $user_name_display . "</a>" : 'N/A';
                    // Truncate long messages for display in the table
                    $message_display = strlen($record['message']) > 100 ? substr(htmlspecialchars($record['message']), 0, 97) . '...' : htmlspecialchars($record['message']);
                    $rating_display = $record['rating'] !== null ? str_repeat('<span class="text-warning">&#9733;</span>', $record['rating']) . str_repeat('<span class="text-muted">&#9734;</span>', 5 - $record['rating']) : '<span class="text-muted">No Rating</span>';

                    $html .= "
                            <tr>
                                <td>" . htmlspecialchars($record['id']) . "</td>
                                <td>" . ($user_role === 'admin' ? $user_link : $user_name_display) . "</td> <!-- Show link only to admins -->
                                <td>" . $message_display . "</td>
                                <td>" . $rating_display . "</td>
                                <td>" . htmlspecialchars($record['created_at']) . "</td>
                                <td>
                                    <a href='FeedbackController.php?action=edit&id=" . $record['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                                    <a href='FeedbackController.php?action=delete&id=" . $record['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete feedback ID " . $record['id'] . "? This action cannot be undone and may fail due to data retention policies.\")'>Delete</a>
                                </td>
                            </tr>
                    ";
                }

                $html .= "
                        </tbody>
                    </table>
                </div>
                ";
            } else {
                $html .= "<p>No feedback records found.</p>";
            }
        }
        return $html;
    }

    private function getCreateContent($success_message = '', $error_message = '') {
        $html = "<h2>Submit Feedback</h2><p>We value your opinion! Please share your thoughts.</p>";

        if ($success_message) {
            $html .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
        }
        if ($error_message) {
            $html .= "<div class='alert alert-danger alert-dismissible fade show' role='alert'>" . $error_message . " <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>"; // Don't escape HTML for error messages as they might contain <br>
        }

        $html .= "
            <form method='post' action='../controllers/FeedbackController.php?action=create'>
                <div class='mb-3'>
                    <label for='message' class='form-label'>Your Feedback:</label>
                    <textarea class='form-control' id='message' name='message' rows='4' required>" . htmlspecialchars($_POST['message'] ?? '') . "</textarea>
                </div>
                <div class='mb-3'>
                    <label for='rating' class='form-label'>Rating (Optional):</label>
                    <div>
                        <input type='radio' class='btn-check' name='rating' id='rating1' value='1' autocomplete='off' " . (($_POST['rating'] ?? null) == 1 ? 'checked' : '') . ">
                        <label class='btn btn-outline-warning' for='rating1'>&#9733; 1 Star</label>

                        <input type='radio' class='btn-check' name='rating' id='rating2' value='2' autocomplete='off' " . (($_POST['rating'] ?? null) == 2 ? 'checked' : '') . ">
                        <label class='btn btn-outline-warning' for='rating2'>&#9733;&#9733; 2 Stars</label>

                        <input type='radio' class='btn-check' name='rating' id='rating3' value='3' autocomplete='off' " . (($_POST['rating'] ?? null) == 3 ? 'checked' : '') . ">
                        <label class='btn btn-outline-warning' for='rating3'>&#9733;&#9733;&#9733; 3 Stars</label>

                        <input type='radio' class='btn-check' name='rating' id='rating4' value='4' autocomplete='off' " . (($_POST['rating'] ?? null) == 4 ? 'checked' : '') . ">
                        <label class='btn btn-outline-warning' for='rating4'>&#9733;&#9733;&#9733;&#9733; 4 Stars</label>

                        <input type='radio' class='btn-check' name='rating' id='rating5' value='5' autocomplete='off' " . (($_POST['rating'] ?? null) == 5 ? 'checked' : '') . ">
                        <label class='btn btn-outline-warning' for='rating5'>&#9733;&#9733;&#9733;&#9733;&#9733; 5 Stars</label>

                        <input type='radio' class='btn-check' name='rating' id='rating0' value='' autocomplete='off' " . (($_POST['rating'] ?? null) === '' ? 'checked' : '') . ">
                        <label class='btn btn-outline-secondary' for='rating0'>No Rating</label>
                    </div>
                </div>
                <div class='d-grid'>
                    <button type='submit' class='btn btn-primary'>Submit Feedback</button>
                </div>
            </form>
            <div class='mt-3'>
                <a href='FeedbackController.php?action=index' class='btn btn-secondary'>Back to My Feedback</a>
            </div>
        ";

        return $html;
    }

     private function getEditContent($feedback_details, $success_message = '', $error_message = '') {
        $html = "<h2>Edit Feedback</h2>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
             $html .= "<a href='FeedbackController.php?action=index' class='btn btn-secondary'>Back to Feedback List</a>";
        } elseif ($feedback_details) {
            $user_name_display = $feedback_details['user_first_name'] ? htmlspecialchars($feedback_details['user_first_name'] . ' ' . $feedback_details['user_last_name']) : htmlspecialchars($feedback_details['user_username']);
            $user_link = "<a href='../views/users/view.php?id=" . $feedback_details['user_id'] . "'>" . $user_name_display . "</a>";
            $rating_display = $feedback_details['rating'] !== null ? str_repeat('<span class="text-warning">&#9733;</span>', $feedback_details['rating']) . str_repeat('<span class="text-muted">&#9734;</span>', 5 - $feedback_details['rating']) : '<span class="text-muted">No Rating</span>';

            $html .= "<p>Editing feedback ID: <strong>" . htmlspecialchars($feedback_details['id']) . "</strong>, Submitted by: <strong>" . $user_link . "</strong></p>";

            if ($success_message) {
                $html .= "<div class='alert alert-success alert-dismissible fade show' role='alert'>" . htmlspecialchars($success_message) . "<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>";
            }

            $html .= "
            <form method='post' action='../controllers/FeedbackController.php?action=edit&id=" . $feedback_details['id'] . "'>
                <div class='mb-3'>
                    <label for='feedback_id_display' class='form-label'>Feedback ID (Read-only):</label>
                    <input type='text' class='form-control-plaintext' id='feedback_id_display' value='" . htmlspecialchars($feedback_details['id']) . "' readonly>
                </div>
                <div class='mb-3'>
                    <label for='message' class='form-label'>Feedback Message:</label>
                    <textarea class='form-control' id='message' name='message' rows='4' required>" . htmlspecialchars($feedback_details['message']) . "</textarea>
                </div>
                <div class='mb-3'>
                    <label for='rating' class='form-label'>Rating (Optional):</label>
                    <div>
                        <input type='radio' class='btn-check' name='rating' id='rating1' value='1' autocomplete='off' " . ($feedback_details['rating'] == 1 ? 'checked' : '') . ">
                        <label class='btn btn-outline-warning' for='rating1'>&#9733; 1 Star</label>

                        <input type='radio' class='btn-check' name='rating' id='rating2' value='2' autocomplete='off' " . ($feedback_details['rating'] == 2 ? 'checked' : '') . ">
                        <label class='btn btn-outline-warning' for='rating2'>&#9733;&#9733; 2 Stars</label>

                        <input type='radio' class='btn-check' name='rating' id='rating3' value='3' autocomplete='off' " . ($feedback_details['rating'] == 3 ? 'checked' : '') . ">
                        <label class='btn btn-outline-warning' for='rating3'>&#9733;&#9733;&#9733; 3 Stars</label>

                        <input type='radio' class='btn-check' name='rating' id='rating4' value='4' autocomplete='off' " . ($feedback_details['rating'] == 4 ? 'checked' : '') . ">
                        <label class='btn btn-outline-warning' for='rating4'>&#9733;&#9733;&#9733;&#9733; 4 Stars</label>

                        <input type='radio' class='btn-check' name='rating' id='rating5' value='5' autocomplete='off' " . ($feedback_details['rating'] == 5 ? 'checked' : '') . ">
                        <label class='btn btn-outline-warning' for='rating5'>&#9733;&#9733;&#9733;&#9733;&#9733; 5 Stars</label>

                        <input type='radio' class='btn-check' name='rating' id='rating0' value='' autocomplete='off' " . ($feedback_details['rating'] === null ? 'checked' : '') . ">
                        <label class='btn btn-outline-secondary' for='rating0'>No Rating</label>
                    </div>
                </div>
                <div class='mb-3'>
                    <label for='submitted_at_display' class='form-label'>Submitted At (Read-only):</label>
                    <input type='text' class='form-control-plaintext' id='submitted_at_display' value='" . htmlspecialchars($feedback_details['created_at']) . "' readonly>
                </div>
                <div class='d-grid'>
                    <button type='submit' class='btn btn-primary'>Update Feedback</button>
                </div>
            </form>
            <div class='mt-3'>
                <a href='FeedbackController.php?action=index' class='btn btn-secondary'>Back to Feedback List</a>
                <!-- View details link if a dedicated view page exists -->
                <!-- <a href='FeedbackController.php?action=view&id=" . $feedback_details['id'] . "' class='btn btn-info'>View Feedback Details</a> -->
            </div>
            ";
        } else {
            // This case handles when the feedback ID was provided but the record wasn't found or access denied (error_message is set above)
            $html .= "<p>Unable to load feedback information.</p>";
            $html .= "<a href='FeedbackController.php?action=index' class='btn btn-secondary'>Back to Feedback List</a>";
        }

        return $html;
    }

    private function getDeleteContent($feedback_details, $success_message = '', $error_message = '') {
        $html = "<h2>Delete Feedback</h2>";

        if ($error_message) {
             $html .= "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($error_message) . "</div>";
             $html .= "<a href='FeedbackController.php?action=index' class='btn btn-secondary'>Back to Feedback List</a>";
        } elseif ($feedback_details) {
            if ($success_message) {
                $html .= "<div class='alert alert-success' role='alert'>" . htmlspecialchars($success_message) . "</div>";
                $html .= "<a href='FeedbackController.php?action=index' class='btn btn-secondary'>Back to Feedback List</a>";
            } else {
                $user_name_display = $feedback_details['user_first_name'] ? htmlspecialchars($feedback_details['user_first_name'] . ' ' . $feedback_details['user_last_name']) : htmlspecialchars($feedback_details['user_username']);
                $user_link = "<a href='../views/users/view.php?id=" . $feedback_details['user_id'] . "'>" . $user_name_display . "</a>";
                $rating_display = $feedback_details['rating'] !== null ? str_repeat('<span class="text-warning">&#9733;</span>', $feedback_details['rating']) . str_repeat('<span class="text-muted">&#9734;</span>', 5 - $feedback_details['rating']) : '<span class="text-muted">No Rating</span>';
                $message_display = nl2br(htmlspecialchars($feedback_details['message'])); // Preserve line breaks

                $html .= "
                <div class='alert alert-warning' role='alert'>
                    <h5>Confirm Deletion</h5>
                    <p>Are you sure you want to delete the following feedback record?</p>
                    <p><strong>ID:</strong> " . htmlspecialchars($feedback_details['id']) . "</p>
                    <p><strong>User:</strong> " . $user_link . "</p>
                    <p><strong>Message:</strong> " . $message_display . "</p>
                    <p><strong>Rating:</strong> " . $rating_display . "</p>
                    <p><strong>Submitted At:</strong> " . htmlspecialchars($feedback_details['created_at']) . "</p>
                    <p><strong>WARNING: This action cannot be undone and may fail due to data retention policies or database constraints.</strong></p>
                </div>
                <form method='post' action='../controllers/FeedbackController.php?action=delete&id=" . $feedback_details['id'] . "'>
                    <input type='hidden' name='id' value='" . htmlspecialchars($feedback_details['id']) . "'>
                    <input type='hidden' name='confirm_delete' value='1'>
                    <button type='submit' class='btn btn-danger'>Yes, Delete Feedback</button>
                    <a href='FeedbackController.php?action=index' class='btn btn-secondary'>Cancel</a>
                </form>
                ";
            }
        } else {
            // This case handles when the feedback ID was provided but the record wasn't found or access denied (error_message is set above)
            $html .= "<p>Unable to load feedback information for deletion.</p>";
            $html .= "<a href='FeedbackController.php?action=index' class='btn btn-secondary'>Back to Feedback List</a>";
        }

        return $html;
    }

}

// --- Routing ---
$action = $_GET['action'] ?? '';

$feedbackController = new FeedbackController();

// Ensure user is logged in before processing actions
requireLogin(); // This check happens *after* controller instantiation so helper functions can use models

// Call the appropriate method based on the action
switch ($action) {
    case 'index':
        $feedbackController->index();
        break;
    case 'create':
        $feedbackController->create();
        break;
    case 'edit':
        $feedbackController->edit();
        break;
    case 'delete':
        $feedbackController->delete();
        break;
    default:
        // Default to index if action is invalid or not provided
        $feedbackController->index();
        break;
}

?>