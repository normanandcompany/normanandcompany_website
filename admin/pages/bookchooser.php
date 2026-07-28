<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

requireRole('admin');
?>

<section class="book-chooser-page">
    <div
        id="page-title-meta"
        data-title="Norman and Company | Sweepstakes Winner Selector"
        style="display: none;">
    </div>

    <div class="book-chooser-header">
        <div>
            <h1>Sweepstakes Winner Selector</h1>
            <p>Randomly select an eligible adult sweepstakes participant to receive a complimentary book.</p>
        </div>

        <button type="button" class="btn-primary book-chooser-button" id="chooseBookRecipientBtn">
            Choose a Customer
        </button>
    </div>

    <div id="bookChooserAlert" class="product-alert" role="status" aria-live="polite" hidden></div>

    <div class="book-chooser-empty" id="bookChooserEmpty">
        <h2>No customer selected yet</h2>
        <p>Eligible customers must be active sweepstakes participants, at least 18 years old, and must not have won previously.</p>
    </div>

    <article class="book-recipient-card" id="bookRecipientCard" aria-live="polite" hidden>
        <div class="book-recipient-heading">
            <div>
                <p class="section-kicker">Selected Recipient</p>
                <h2 id="bookRecipientName"></h2>
            </div>
            <span class="book-recipient-badge">Complimentary Book</span>
        </div>

        <div class="book-recipient-grid">
            <section>
                <h3>Contact Information</h3>
                <dl>
                    <div>
                        <dt>Email</dt>
                        <dd><a id="bookRecipientEmail"></a></dd>
                    </div>
                    <div>
                        <dt>Phone</dt>
                        <dd><a id="bookRecipientPhone"></a></dd>
                    </div>
                </dl>
            </section>

            <section>
                <h3>Shipping Address</h3>
                <address id="bookRecipientAddress"></address>
            </section>

            <section>
                <h3>Account Details</h3>
                <dl>
                    <div>
                        <dt>Customer ID</dt>
                        <dd id="bookRecipientId"></dd>
                    </div>
                    <div>
                        <dt>Customer Since</dt>
                        <dd id="bookRecipientSince"></dd>
                    </div>
                    <div>
                        <dt>Age</dt>
                        <dd id="bookRecipientAge"></dd>
                    </div>
                    <div>
                        <dt>Drawing Date</dt>
                        <dd id="bookRecipientDrawingDate"></dd>
                    </div>
                </dl>
            </section>
        </div>
    </article>
</section>
