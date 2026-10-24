<?php
require_once __DIR__ . '/header.php';
?>
<div class="container py-5 text-center">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm p-4">
                <div class="mb-3 text-danger">
                    <i class="bi bi-shield-slash" style="font-size: 4rem;"></i>
                </div>
                <h3 class="fw-bold text-navy mb-2">Access Restricted</h3>
                <p class="text-muted mb-4">You do not have the required role or permission to access this module or resource. Please contact your store administrator if you believe this is in error.</p>
                <div>
                    <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary px-4 me-2">
                        <i class="bi bi-house me-1"></i> Return to Dashboard
                    </a>
                    <a href="javascript:history.back()" class="btn btn-outline-secondary px-4">
                        <i class="bi bi-arrow-left me-1"></i> Go Back
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
require_once __DIR__ . '/footer.php';
?>
