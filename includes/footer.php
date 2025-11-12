<?php
// includes/footer.php
?>
            </main>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container-fluid">
            <span class="text-muted">&copy; <?php echo date("Y"); ?> Mess Management System. All rights reserved.</span>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js" integrity="sha384-w76AqPfDkMBDXo30jS1Sgez6pr3x5MlQ1ZAGC+nuZB+EYdgRZgiwxhTBTkF7CXvN" crossorigin="anonymous"></script>
    <!-- Custom JS -->
    <script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>
    <!-- You can add more specific JS for different sections here if needed -->
    <?php if (defined('ENABLE_DEBUG_MODE') && ENABLE_DEBUG_MODE): ?>
    <!-- Optional: Debugging JS -->
    <!-- <script src="<?php echo BASE_URL; ?>assets/js/debug.js"></script> -->
    <?php endif; ?>
</body>
</html>