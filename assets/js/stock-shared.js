// ============================================================
// stock-shared.js — shared across all Stock module pages
// (stock.php, stock-history.php, stock-in-new.php,
//  stock-adjust-new.php, stock-txn-details.php)
// ============================================================
const STATE = { products: [], stock: [], suppliers: [], team: [] };
const BIZ_FROM_DATE = '2026-05-01';

// Fetches the lookups every Stock page needs (products + suppliers are
// used directly with no lazy-load guard in the extracted functions below,
// so they must be populated before those functions run).
async function bootStockPageState() {
  try {
    const [prod, stk, sup] = await Promise.all([
      api('/api/products.php'),
      api('/api/stock.php'),
      api('/api/suppliers.php'),
    ]);
    STATE.products  = Array.isArray(prod.data) ? prod.data : [];
    STATE.stock     = Array.isArray(stk.data)  ? stk.data  : [];
    STATE.suppliers = Array.isArray(sup.data)  ? sup.data  : [];
  } catch (e) {
    toast('❌ Failed to load product/stock data: ' + e.message, 'error');
  }
}

async function renderStock() { await renderProductStock(); }

function snAvailableStockSafe(productId) {
  const s = (STATE.stock||[]).find(x => String(x.product_id) === String(productId).replace(/\D/g,''));
  return s ? parseFloat(s.current_stock ?? s.available_stock) || 0 : 0;
}


