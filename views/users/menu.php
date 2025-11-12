<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../auth/login.php");
    exit;
}

$table_qr = $_GET['table_qr'] ?? '';
if(!$table_qr){ die("Invalid access. No table QR."); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Table Details</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h3 class="text-center">Enter Table Details</h3>
    <form method="GET" action="menuu.php" class="mx-auto" style="max-width:400px;">
        <input type="hidden" name="table_qr" value="<?php echo htmlspecialchars($table_qr); ?>">

        <div class="mb-3">
            <label>Number of Credits</label>
            <input type="number" name="credits" id="credits" min="1" max="4" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Seating</label>
            <input type="text" name="seating" id="seating" class="form-control" readonly>
        </div>

        <button type="submit" class="btn btn-primary w-100">Next</button>
    </form>
</div>

<script>
const creditsInput = document.getElementById('credits');
const seatingInput = document.getElementById('seating');

creditsInput.addEventListener('input', () => {
    const val = parseInt(creditsInput.value);
    if(val >= 1 && val <= 4) seatingInput.value = val + "/4";
    else seatingInput.value = '';
});
</script>
</body>
</html>
