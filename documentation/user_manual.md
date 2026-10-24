# MartPro Enterprise Retail POS - User Manual

## 1. High-Speed POS Checkout
- **Barcode Scanning:** Focus is automatically maintained on the scanning field. Scan barcode with your USB or Bluetooth barcode scanner.
- **Quantity Multipliers:** Type `5*890123450002` to immediately add 5 units of Coca-Cola without scanning 5 individual times.
- **Weighable Produce:** Scanned barcodes starting with `20` automatically extract the PLU code and kilograms from the embedded barcode. Alternatively, click the **Scale** button to capture live weight from an attached RS-232 scale.
- **Discounts:** Click `[Edit]` next to Bill Discount to apply an overall cart reduction, or enter line item discounts.
- **Split Tenders:** Press `F8` to open the payment dialog. Enter partial amounts across Cash, Credit Card, Digital QR, or Customer Khata.
- **Quick Cash:** Press `F9` for instant 1-tap exact cash checkout and receipt printing.
- **Hold & Recall Bills:** Press `F4` to park a customer's active cart when they forget an item. Press `F7` to recall suspended bills.

## 2. Inventory & Stock Controls
- **Stock Movements Ledger:** MartPro does not just mutate a single count; every sale, return, adjustment, and GRN creates an immutable audit movement in `stock_movements`.
- **FIFO & FEFO Batch Allocation:** Products requiring expiry tracking allocate stock using First Expired First Out (FEFO) to eliminate perishable store waste.
- **Stock Adjustments:** Record damaged, broken, or expired merchandise with required reason codes and managerial authorization.
- **Physical Count / Stocktake:** Open the Live Count Worksheet in `modules/stock_counts/`, count actual shelves, and post variances to automatically reconcile database numbers.

## 3. Purchasing & Supplier Management
- **Purchase Orders (PO):** Create supplier orders with expected delivery dates and agreed prices.
- **Goods Receiving Notes (GRN):** Compare received items against POs, record batch numbers, expiry dates, and update inventory and vendor payable liabilities.
- **Accounts Payable Settlements:** Record payments to vendors via Bank Wire, Cash, or Cheque, instantly reducing balances.

## 4. Cashier Shift & Cash Drawer Control
- **Opening Shifts:** Enter opening cash float placed in the register drawer.
- **Safe Drops:** Transfer excess drawer cash to the store drop vault during busy shifts to prevent high drawer exposures.
- **X-Reports:** Print a mid-shift summary without closing or resetting totals.
- **Blind Z-Reports:** At shift close, the cashier enters the counted drawer cash blindly without seeing expected totals. The system computes overages/shortages and finalizes the period.
