<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';
requireRole('customer');
?>

<section class="resort-review-form-page" aria-labelledby="resortReviewFormTitle">
    <div id="page-title-meta"
        data-title="Norman and Company | Write a Resort Review"
        style="display: none;">
    </div>

    <p>
        <a href="#" id="resortReviewBackLink">&larr; Back to resort details</a>
    </p>

    <article class="card resort-review-form-card">
        <p class="resort-review-eyebrow">Customer review</p>
        <h1 id="resortReviewFormTitle">Write a Review</h1>
        <p id="resortReviewResortName">Loading resort information...</p>

        <div id="resortReviewFormMessage"
            class="resort-review-form-message"
            role="status"
            aria-live="polite"
            hidden>
        </div>

        <form id="resortReviewForm">
            <input type="hidden" id="resortReviewResortId" name="resort_id">

            <div class="form-group">
                <label for="resortReviewRating">Rating</label>
                <select id="resortReviewRating" name="rating" required>
                    <option value="">Choose a rating</option>
                    <option value="5">5 — Excellent</option>
                    <option value="4">4 — Very good</option>
                    <option value="3">3 — Average</option>
                    <option value="2">2 — Below average</option>
                    <option value="1">1 — Poor</option>
                </select>
            </div>

            <div class="form-group">
                <label for="resortReviewTitle">Review title</label>
                <input
                    type="text"
                    id="resortReviewTitle"
                    name="review_title"
                    maxlength="150"
                    required>
            </div>

            <div class="form-group">
                <label for="resortReviewText">Your review</label>
                <textarea
                    id="resortReviewText"
                    name="review_text"
                    rows="8"
                    maxlength="4000"
                    required></textarea>
                <small>Share details that will help other travelers. Maximum 4,000 characters.</small>
            </div>

            <div class="form-group">
                <label for="resortReviewPhoto">Review photo <span class="optional-label">(optional)</span></label>
                <input
                    type="file"
                    id="resortReviewPhoto"
                    name="review_photo"
                    accept="image/jpeg,image/png,image/webp,image/gif">
                <small>Upload a JPEG, PNG, WebP, or GIF image up to 5 MB.</small>
            </div>

            <div id="resortReviewExistingPhoto" class="resort-review-existing-photo" hidden>
                <img id="resortReviewExistingPhotoImage" alt="Current review photo">
                <label>
                    <input type="checkbox" id="resortReviewRemovePhoto" name="remove_photo" value="1">
                    Remove the current photo
                </label>
            </div>

            <button type="submit" class="resort-review-submit-button">Publish Review</button>
        </form>
    </article>
</section>
