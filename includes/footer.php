<?php
defined('MARTPRO_INIT') or define('MARTPRO_INIT', true);
?>
        </main>
        
        <!-- App Footer -->
        <footer class="p-3 bg-white border-top text-muted small text-center text-md-start d-flex justify-content-between align-items-center">
            <div>
                &copy; <?= date('Y') ?> <strong>MartPro</strong> &mdash; Smart Store. Better Business.
            </div>
            <div class="d-none d-md-block text-end">
                <span>Enterprise Retail POS & Operations ERP</span>
            </div>
        </footer>
    </div>
</div>

<!-- Floating Notification Toast Container -->
<div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

<!-- Core Local Vendor JS -->
<script src="<?= BASE_URL ?>/assets/js/jquery.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/chart.min.js"></script>

<!-- MartPro Core Scripts -->
<script>
    window.MartPro = window.MartPro || {};
    MartPro.baseUrl = '<?= rtrim(BASE_URL, '/') ?>';
</script>
<script src="<?= BASE_URL ?>/assets/js/ajax-helper.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>

<?php if (isset($extra_js)): ?>
    <script src="<?= $extra_js ?>"></script>
<?php endif; ?>
<?php if (isset($inline_js)): ?>
    <script><?= $inline_js ?></script>
<?php endif; ?>
</body>
</html>
