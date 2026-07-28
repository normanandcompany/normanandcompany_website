<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

requireRole('admin');
?>

<section class="product-manager transaction-manager">

    <div id="page-title-meta"
        data-title="Norman and Company | Transaction Management"
        style="display: none;">
    </div>

    <div class="product-manager-header">
        <div>
            <h1>Transaction Management</h1>
        </div>

        <button type="button" class="btn-secondary" id="refreshTransactionsBtn">
            Refresh
        </button>
    </div>

    <div class="product-toolbar transaction-toolbar" aria-label="Transaction filters">
        <div class="product-search-field">
            <label for="transactionSearchInput">Search</label>
            <input
                type="search"
                id="transactionSearchInput"
                placeholder="Reference, order, customer, or provider"
                autocomplete="off"
            >
        </div>

        <div class="product-filter-field">
            <label for="transactionStatusFilter">Status</label>
            <select id="transactionStatusFilter">
                <option value="all">All Statuses</option>
            </select>
        </div>

        <div class="product-filter-field">
            <label for="transactionTypeFilter">Type</label>
            <select id="transactionTypeFilter">
                <option value="all">All Types</option>
            </select>
        </div>

        <button type="button" class="btn-secondary product-filter-reset" id="resetTransactionFiltersBtn">
            Reset
        </button>
    </div>

    <div class="product-metrics transaction-metrics" aria-label="Transaction summary">
        <div class="product-metric">
            <span id="transactionTotalCount">0</span>
            <small>Total</small>
        </div>
        <div class="product-metric">
            <span id="transactionPostedCount">0</span>
            <small>Posted</small>
        </div>
        <div class="product-metric">
            <span id="transactionGrossAmount">$0.00</span>
            <small>Gross Amount</small>
        </div>
        <div class="product-metric">
            <span id="transactionFilteredCount">0</span>
            <small>Shown</small>
        </div>
    </div>

    <div id="transactionAlert" class="product-alert" role="status" aria-live="polite" hidden></div>

    <div class="product-table-panel">
        <div class="product-table-scroll">
            <table class="product-table transaction-table">
                <thead>
                    <tr>
                        <th>Transaction</th>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>Net Sales</th>
                        <th>Processed</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="transactionsTableBody">
                    <tr>
                        <td colspan="9" class="product-empty-state">Loading transactions...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="product-pagination">
            <button type="button" id="transactionPrevPageBtn" class="btn-secondary">
                Previous
            </button>

            <span id="transactionPageInfo">
                Page 1
            </span>

            <button type="button" id="transactionNextPageBtn" class="btn-secondary">
                Next
            </button>
        </div>
    </div>

    <dialog id="transactionFormDialog" class="product-dialog transaction-dialog">
        <form id="transactionForm" class="product-form">
            <input type="hidden" id="transactionId" name="id">

            <div class="product-dialog-header">
                <div>
                    <p class="section-kicker">Payment Record</p>
                    <h2 id="transactionFormTitle">Edit Transaction</h2>
                </div>

                <button type="button" class="product-dialog-close" id="closeTransactionDialogBtn" aria-label="Close">
                    &times;
                </button>
            </div>

            <div class="product-form-grid transaction-form-grid">
                <div class="product-form-main">
                    <div class="product-field-row three-column">
                        <div class="form-group stacked">
                            <label for="transactionOrderId">Order ID</label>
                            <input
                                type="number"
                                id="transactionOrderId"
                                name="order_id"
                                min="1"
                                step="1"
                                required
                            >
                        </div>

                        <div class="form-group stacked">
                            <label for="transactionReference">Reference</label>
                            <input
                                type="text"
                                id="transactionReference"
                                name="transaction_reference"
                                maxlength="255"
                            >
                        </div>

                        <div class="form-group stacked">
                            <label for="transactionProvider">Provider</label>
                            <input
                                type="text"
                                id="transactionProvider"
                                name="payment_provider"
                                maxlength="100"
                            >
                        </div>
                    </div>

                    <div class="product-field-row three-column">
                        <div class="form-group stacked">
                            <label for="transactionType">Type</label>
                            <input
                                type="text"
                                id="transactionType"
                                name="transaction_type"
                                maxlength="50"
                                required
                            >
                        </div>

                        <div class="form-group stacked">
                            <label for="transactionStatus">Status</label>
                            <input
                                type="text"
                                id="transactionStatus"
                                name="transaction_status"
                                maxlength="100"
                            >
                        </div>

                        <div class="form-group stacked">
                            <label for="transactionCurrencyCode">Currency</label>
                            <input
                                type="text"
                                id="transactionCurrencyCode"
                                name="currency_code"
                                maxlength="3"
                                required
                            >
                        </div>
                    </div>

                    <div class="product-field-row two-column">
                        <div class="form-group stacked">
                            <label for="transactionProcessedAt">Processed At</label>
                            <input
                                type="datetime-local"
                                id="transactionProcessedAt"
                                name="processed_at"
                            >
                        </div>

                        <div class="form-group stacked">
                            <label for="transactionAmount">Transaction Total</label>
                            <input
                                type="number"
                                id="transactionAmount"
                                name="transaction_amount"
                                step="0.01"
                            >
                        </div>
                    </div>

                    <div class="transaction-fieldset">
                        <h3>Financial Amounts</h3>

                        <div class="product-field-row two-column">
                            <div class="form-group stacked">
                                <label for="transactionProductSalesAmount">Product Sales</label>
                                <input
                                    type="number"
                                    id="transactionProductSalesAmount"
                                    name="product_sales_amount"
                                    min="0"
                                    step="0.01"
                                >
                            </div>

                            <div class="form-group stacked">
                                <label for="transactionProductCostAmount">Product Cost</label>
                                <input
                                    type="number"
                                    id="transactionProductCostAmount"
                                    name="product_cost_amount"
                                    min="0"
                                    step="0.01"
                                >
                            </div>
                        </div>

                        <div class="product-field-row two-column">
                            <div class="form-group stacked">
                                <label for="transactionShippingAmount">Shipping</label>
                                <input
                                    type="number"
                                    id="transactionShippingAmount"
                                    name="shipping_amount"
                                    min="0"
                                    step="0.01"
                                >
                            </div>

                            <div class="form-group stacked">
                                <label for="transactionReturnAmount">Returns</label>
                                <input
                                    type="number"
                                    id="transactionReturnAmount"
                                    name="return_amount"
                                    min="0"
                                    step="0.01"
                                >
                            </div>
                        </div>

                        <div class="product-field-row two-column">
                            <div class="form-group stacked">
                                <label for="transactionSalesTaxAmount">Sales Tax</label>
                                <input
                                    type="number"
                                    id="transactionSalesTaxAmount"
                                    name="sales_tax_amount"
                                    min="0"
                                    step="0.01"
                                >
                            </div>

                            <div class="form-group stacked">
                                <label for="transactionDiscountAmount">Discounts</label>
                                <input
                                    type="number"
                                    id="transactionDiscountAmount"
                                    name="discount_amount"
                                    min="0"
                                    step="0.01"
                                >
                            </div>
                        </div>

                        <div class="product-field-row two-column">
                            <div class="form-group stacked">
                                <label for="transactionPaymentFeeAmount">Payment Fee</label>
                                <input
                                    type="number"
                                    id="transactionPaymentFeeAmount"
                                    name="payment_fee_amount"
                                    min="0"
                                    step="0.01"
                                >
                            </div>

                            <div class="form-group stacked">
                                <label for="transactionNetPreviewInput">Net Sales</label>
                                <input
                                    type="text"
                                    id="transactionNetPreviewInput"
                                    readonly
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="product-form-side">
                    <div class="transaction-summary-card">
                        <h3>Order Context</h3>
                        <dl>
                            <div>
                                <dt>Order</dt>
                                <dd id="transactionPreviewOrder">N/A</dd>
                            </div>
                            <div>
                                <dt>Customer</dt>
                                <dd id="transactionPreviewCustomer">N/A</dd>
                            </div>
                            <div>
                                <dt>Order Total</dt>
                                <dd id="transactionPreviewOrderTotal">$0.00</dd>
                            </div>
                            <div>
                                <dt>Gross Profit</dt>
                                <dd id="transactionGrossPreview">$0.00</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="product-toggle-grid" aria-label="Transaction flags">
                        <label class="toggle-row">
                            <input type="checkbox" id="transactionVisible" name="visible" value="1" checked>
                            <span>Visible</span>
                        </label>
                    </div>
                </aside>
            </div>

            <div class="product-dialog-actions">
                <button type="button" class="btn-secondary" id="cancelTransactionBtn">
                    Cancel
                </button>

                <button type="submit" class="btn-primary" id="saveTransactionBtn">
                    Save Transaction
                </button>
            </div>
        </form>
    </dialog>
</section>
