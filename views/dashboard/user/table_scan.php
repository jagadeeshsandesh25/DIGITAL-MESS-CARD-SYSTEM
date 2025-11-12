<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// views/dashboard/user/table_scan.php
session_start();

// Use your existing auth check (you might already have this in auth.php — but this is fine inline for views)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../auth/login.php");
    exit;
}

// 🔥 Load DB config (Database class definition)
require_once '../../../config/database.php';

// 🔥 Create an instance of the Database class and get the PDO connection
$database = new Database();
$pdo = $database->getConnection();

// 🔥 Check if the connection was successful before proceeding
if ($pdo === null) {
    // If getConnection returned null, it means the connection failed.
    // You should handle this appropriately, maybe show an error page.
    // For now, let's just die with an error message.
    die("Database connection could not be established.");
}

// 🔥 Fetch only available table QR codes
$stmt = $pdo->prepare("SELECT t_qr FROM tables WHERE status = 'available'");
$stmt->execute();
$validTableQrs = $stmt->fetchAll(PDO::FETCH_COLUMN); // Returns simple array like ['TABLE_01', 'TABLE_02', ...]
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Table QR - Mess Management</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- QR Scanner -->
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        :root {
            --primary-color: #284194;
            --secondary-color: #ff8c42;
            --success-color: #6ee7b7;
            --warning-color: #fcd34d;
            --info-color: #a78bfa;
            --dark-bg: #121212;
            --light-bg: #f5f7fa;
        }

        body {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding-top: 64px;
        }

        .navbar {
            background: white !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            z-index: 1000;
        }

        .navbar-brand {
            font-weight: 600;
            letter-spacing: -0.5px;
            color: var(--primary-color) !important;
        }

        .navbar-nav .nav-link {
            color: #64748b !important;
            font-weight: 500;
            padding: 0.5rem 0.75rem !important;
        }

        .navbar-nav .nav-link:hover {
            color: var(--primary-color) !important;
        }

        .scanner-container {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        #reader {
            width: 100%;
            max-width: 350px;
            border: 2px dashed var(--primary-color);
            border-radius: 1rem;
            overflow: hidden;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
        }

        .footer-btn {
            text-align: center;
            margin: 1.5rem 0;
        }

        .table-info {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--primary-color);
            margin-top: 1rem;
            text-align: center;
        }

        .qr-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #94a3b8;
            text-align: center;
            padding: 1rem;
        }

        .qr-placeholder i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        /* Mobile-specific styles */
        @media (max-width: 576px) {
            body { padding-top: 64px; }
            
            .navbar-brand {
                font-size: 1.1rem;
            }

            .navbar-nav .nav-link {
                padding: 0.5rem 0.5rem !important;
                font-size: 0.85rem;
            }

            .container-fluid {
                padding: 0 0.75rem;
            }

            #reader {
                height: 250px;
            }

            .table-info {
                font-size: 1rem;
            }

            .btn-lg {
                padding: 0.5rem 1rem;
                font-size: 1rem;
            }

            .scanner-container {
                padding: 0.75rem;
            }
        }

        @media (max-width: 768px) {
            .text-center h3 {
                font-size: 1.25rem;
            }

            .text-center p {
                font-size: 0.9rem;
            }
        }

        .sticky-footer {
            position: sticky;
            top: 100vh;
            background: white;
            padding: 0.5rem;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.08);
            z-index: 100;
        }

        body.dark-mode .sticky-footer {
            background: #1e1e1e;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-primary">Scan Your Table QR</h3>
            <p class="text-muted">Point your camera at the table QR (TABLE_01–TABLE_06)</p>
        </div>

        <div class="scanner-container">
            <div id="reader">
                <div class="qr-placeholder">
                    <div>
                        <i class="bi bi-qr-code-scan text-primary"></i>
                        <p class="mb-0">Camera loading...</p>
                    </div>
                </div>
            </div>
            <div class="table-info" id="result">Waiting for QR scan...</div>
        </div>

        <div class="footer-btn">
            <a href="index.php" class="btn btn-secondary btn-lg">
                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Sticky footer for mobile -->
    <div class="sticky-footer d-md-none">
        <div class="d-flex justify-content-around">
            <a href="index.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-speedometer2"></i></a>
            <a href="profile.php" class="btn btn-outline-success btn-sm"><i class="bi bi-person"></i></a>
            <a href="../../../logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>

    <script>
        // ✅ Inject real table QRs from DB — this is the ONLY JS change
        const validTables = <?php echo json_encode($validTableQrs); ?>;

        function onScanSuccess(decodedText) {
            const resultElem = document.getElementById('result');

            if (validTables.includes(decodedText)) {
                resultElem.innerHTML = `✅ Table scanned successfully: <strong>${decodedText}</strong>`;
                resultElem.classList.remove('text-danger');
                resultElem.classList.add('text-success');

                html5QrCode.stop().then(() => {
                    setTimeout(() => {
                        window.location.href = `menu.php?table_qr=${encodeURIComponent(decodedText)}`;
                    }, 1000);
                }).catch(err => console.error("Stop failed:", err));
            } else {
                resultElem.innerHTML = `❌ Invalid QR: <strong>${decodedText}</strong>`;
                resultElem.classList.remove('text-success');
                resultElem.classList.add('text-danger');
            }
        }

        function onScanError(errorMessage) {
            // ignore minor read errors
        }

        const html5QrCode = new Html5Qrcode("reader");

        Html5Qrcode.getCameras().then(devices => {
            if (devices && devices.length) {
                html5QrCode.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: 250 },
                    onScanSuccess,
                    onScanError
                );
            } else {
                document.getElementById('result').innerText = "No camera detected.";
            }
        }).catch(err => {
            document.getElementById('result').innerText = "Camera access denied or not available.";
        });
    </script>
</body>
</html>