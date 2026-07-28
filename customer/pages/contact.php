<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

requireRole('customer');
?>

<section class="content-section">

    <!-- Page Title Metadata -->
    <div id="page-title-meta"
        data-title="Norman and Company | Contact Us"
        style="display: none;">
    </div>

    <!-- PAGE TITLE -->

    <h1>Contact Norman and Company</h1>

    <p class="page-intro">
        Have questions about cruises, Caribbean destinations,
        travel essentials, or products in our store?
        We'd love to hear from you.
    </p>

    <!-- CONTACT CARD -->

    <div class="card">

        <form id="contactForm">

            <!-- FULL NAME -->

            <div class="form-group">

                <label for="fullName">
                    Full Name
                </label>

                <input
                    type="text"
                    id="fullName"
                    name="fullName"
                    placeholder="Enter your full name"
                    required
                >

            </div>

            <!-- EMAIL -->

            <div class="form-group">

                <label for="email">
                    Email Address
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email address"
                    required
                >

            </div>

            <!-- PHONE -->

            <div class="form-group">

                <label for="phone">
                    Phone Number
                </label>

                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    placeholder="Optional phone number"
                >

            </div>

            <!-- SUBJECT -->

            <div class="form-group">

                <label for="subject">
                    Subject
                </label>

                <select
                    id="subject"
                    name="subject"
                    required
                >

                    <option value="">
                        Select a topic
                    </option>

                    <option value="travel-question">
                        Travel Question
                    </option>

                    <option value="product-question">
                        Product Question
                    </option>

                    <option value="partnership">
                        Partnership Inquiry
                    </option>

                    <option value="general">
                        General Inquiry
                    </option>

                    <option value="customer-complaint">
                        Customer Complaint
                    </option>

                </select>

            </div>

            <!-- MESSAGE -->

            <div class="form-group">

                <label for="message">
                    Message
                </label>

                <textarea
                    id="message"
                    name="message"
                    rows="8"
                    placeholder="Enter your message here..."
                    required
                ></textarea>

            </div>

            <!-- SUBMIT BUTTON -->

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn-primary"
                >
                    Send Message
                </button>

            </div>

        </form>

    </div>

</section>