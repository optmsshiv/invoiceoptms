<!-- Payment Receipt Modal — shared by payments-product.php and
     payments-service.php (viewReceipt / viewReceiptSvc). -->
<div class="modal-overlay" id="modal-receipt">
  <div class="modal modal-md">
    <div class="modal-header"><span>Payment Receipt</span><button class="modal-close" onclick="closeModal('modal-receipt')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body" id="receiptBody" style="padding:24px;max-height:70vh;overflow-y:auto"></div>
    <div class="modal-footer">
      <button class="btn btn-primary" onclick="printReceiptModal()"><i class="fas fa-print"></i> Print Receipt</button>
      <button class="btn btn-outline" onclick="closeModal('modal-receipt')">Close</button>
    </div>
  </div>
</div>
