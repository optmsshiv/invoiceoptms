    </div><!-- /.page -->
  </div><!-- /.pages-container -->
</div><!-- /.main-wrap -->

<!-- ══════════════════════════════════════════
     GLOBAL MODALS
     Shared across multiple pages (e.g. Promise-to-Pay is opened
     from both invoices.php and reminders.php). Page-specific modals
     stay in that page's own file, not here.
══════════════════════════════════════════ -->
<?php include __DIR__ . '/modals/promise_to_pay.php'; ?>
<?php include __DIR__ . '/modals/balance_reminder.php'; ?>
<?php include __DIR__ . '/modals/invoice_preview.php'; ?>
<?php include __DIR__ . '/modals/mark_paid.php'; ?>

<div class="toast-container" id="toastContainer"></div>

<!-- ══════════════════════════════════════════
     SCRIPTS
══════════════════════════════════════════ -->
<script src="/assets/js/common.js"></script>
<?php
// A page can set either $pageScript (single file) or $pageScripts
// (ordered array, e.g. shared-data.js before dashboard.js).
$__scripts = !empty($pageScripts) ? $pageScripts : (!empty($pageScript) ? [$pageScript] : []);
foreach ($__scripts as $__src): ?>
<script src="<?= htmlspecialchars($__src) ?>"></script>
<?php endforeach; ?>

<?php renderSessionTimeoutAssets(); ?>
</body>
</html>
