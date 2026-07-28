<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';
requireRole('customer');
?>

<section class="ship-review-form-page" aria-labelledby="shipReviewFormTitle">
    <div id="page-title-meta"
        data-title="Norman and Company | Write a Ship Review"
        style="display: none;">
    </div>

    <p>
        <a href="#" id="shipReviewBackLink">&larr; Back to ship details</a>
    </p>

    <article class="card ship-review-form-card">
        <p class="ship-review-eyebrow">Customer review</p>
        <h1 id="shipReviewFormTitle">Write a Review</h1>
        <p id="shipReviewShipName">Loading ship information...</p>

        <div id="shipReviewFormMessage"
            class="ship-review-form-message"
            role="status"
            aria-live="polite"
            hidden>
        </div>

        <form id="shipReviewForm">
            <input type="hidden" id="shipReviewShipId" name="ship_id">

            <div class="form-group">
                <label for="shipReviewRating">Rating</label>
                <select id="shipReviewRating" name="rating" required>
                    <option value="">Choose a rating</option>
                    <option value="5">5 — Excellent</option>
                    <option value="4">4 — Very good</option>
                    <option value="3">3 — Average</option>
                    <option value="2">2 — Below average</option>
                    <option value="1">1 — Poor</option>
                </select>
            </div>

            <div class="form-group">
                <label for="shipReviewTitle">Review title</label>
                <input
                    type="text"
                    id="shipReviewTitle"
                    name="review_title"
                    maxlength="150"
                    required>
            </div>

            <div class="form-group">
                <label for="shipReviewText">Your review</label>
                <textarea
                    id="shipReviewText"
                    name="review_text"
                    rows="8"
                    maxlength="4000"
                    required></textarea>
                <small>Share details that will help other travelers. Maximum 4,000 characters.</small>
            </div>

            <div class="form-group">
                <label for="shipReviewPhoto">Review photo <span class="optional-label">(optional)</span></label>
                <input
                    type="file"
                    id="shipReviewPhoto"
                    name="review_photo"
                    accept="image/jpeg,image/png,image/webp,image/gif">
                <small>Upload a JPEG, PNG, WebP, or GIF image up to 5 MB.</small>
            </div>

            <div id="shipReviewExistingPhoto" class="ship-review-existing-photo" hidden>
                <img id="shipReviewExistingPhotoImage" alt="Current review photo">
                <label>
                    <input type="checkbox" id="shipReviewRemovePhoto" name="remove_photo" value="1">
                    Remove the current photo
                </label>
            </div>

            <button type="submit" class="ship-review-submit-button">Publish Review</button>
        </form>
    </article>
</section>
