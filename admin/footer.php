    </div><!-- Close main-content-wrapper -->

    <style>
    /* Make skip link visible when focused */
    .skip-link {
        position: absolute;
        left: -9999px;
        top: auto;
        width: 1px;
        height: 1px;
        overflow: hidden;
        z-index: 2000;
    }
    .skip-link:focus, .visually-hidden-focusable:focus {
        position: static;
        width: auto;
        height: auto;
        margin: 1rem;
        padding: .5rem 1rem;
        background: #0d6efd;
        color: #fff;
        border-radius: .25rem;
        text-decoration: none;
    }
    </style>

    <!-- Footer -->
    <footer class="admin-footer bg-dark text-white py-3 mt-auto">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">
                        &copy; <?= date('Y') ?> SOSOL Financial Platform. All rights reserved.
                        <span class="text-muted ms-2">v<?= $adminVersion ?></span>
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="text-muted small">
                        <i class="fas fa-server me-1"></i> Server: <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') ?> |
                        <i class="fas fa-database me-1"></i> MySQL: <?php
                        try {
                            $dbVersion = 'Unknown';
                            if (getenv('DB_HOST') && getenv('DB_USER')) {
                                $pdo = new PDO('mysql:host=' . getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASSWORD'));
                                $dbVersion = 'v' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
                            }
                        } catch (Exception $e) {
                            $dbVersion = 'Unknown';
                        }
                        echo htmlspecialchars($dbVersion);
                        ?> |
                        <i class="fas fa-clock me-1"></i> <?= date('Y-m-d H:i:s') ?>
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <!-- jQuery (required by DataTables) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom Admin JS (with cache-busting) -->
    <?php
    $adminJsPath = __DIR__ . '/../public/js/admin.js';
    $adminJsVersion = file_exists($adminJsPath) ? filemtime($adminJsPath) : time();
    ?>
    <script src="<?= APP_URL ?>/public/js/admin.js?v=<?= $adminJsVersion ?>"></script>
</body>
</html>