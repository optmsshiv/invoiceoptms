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

<!-- ══════════════════════════════════════════
     SCRIPTS
══════════════════════════════════════════ -->
<script src="/assets/js/common.js"></script>
<?php if (!empty($pageScript)): ?>
<script src="<?= htmlspecialchars($pageScript) ?>"></script>
<?php endif; ?>
</body>
</html>
