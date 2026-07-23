<!-- ========================================= -->
<!-- CUSTOMER PROFILE PAGE -->
<!-- File: /customer/pages/home.php -->
<!-- Copyright 2026 @ Norman & Company -->
<!-- ========================================= -->

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';
requireRole('customer');
?>

<section class="customer-profile" id="customerProfilePage" aria-labelledby="customerProfileTitle">
    <div id="page-title-meta"
        data-title="Norman and Company | My Profile"
        style="display: none;">
    </div>

    <header class="customer-profile__hero">
        <div>
            <p class="customer-profile__eyebrow">Member dashboard</p>
            <h1 id="customerProfileTitle">My Profile</h1>
            <p>Review your account, purchases, and saved travel ideas in one place.</p>
        </div>
        <div class="customer-profile__avatar" id="customerProfileAvatar" aria-hidden="true">NC</div>
    </header>

    <div id="customerProfileAlert" class="customer-profile__alert" role="status" aria-live="polite" hidden></div>

    <div id="customerProfileLoading" class="customer-profile__loading" role="status">
        Loading your profile...
    </div>

    <div id="customerProfileContent" hidden>
        <div class="customer-profile__summary-grid">
            <article class="customer-profile__panel">
                <div class="customer-profile__panel-heading">
                    <div>
                        <p class="customer-profile__eyebrow">Account details</p>
                        <h2 id="customerProfileName">Customer</h2>
                    </div>
                    <div class="customer-profile__account-actions">
                        <span class="customer-profile__badge">Customer</span>
                        <button type="button" class="customer-profile__add-button customer-profile__edit-button" id="customerProfileEditButton" aria-expanded="false" aria-controls="customerProfileEditForm">
                            Edit details
                        </button>
                    </div>
                </div>

                <dl class="customer-profile__details" id="customerProfileDetails"></dl>

                <form id="customerProfileEditForm" class="customer-profile__edit-form" aria-hidden="true" hidden>
                    <div class="customer-profile__form-grid customer-profile__form-grid--two">
                        <div>
                            <label for="customerFirstName">First name</label>
                            <input type="text" id="customerFirstName" name="first_name" maxlength="100" autocomplete="given-name" required>
                        </div>
                        <div>
                            <label for="customerLastName">Last name</label>
                            <input type="text" id="customerLastName" name="last_name" maxlength="100" autocomplete="family-name" required>
                        </div>
                    </div>

                    <div class="customer-profile__form-grid customer-profile__form-grid--two">
                        <div>
                            <label for="customerEmailAddress">Email address</label>
                            <input type="email" id="customerEmailAddress" name="email_address" maxlength="255" autocomplete="email" required>
                            <small>Changing this also changes the email used to sign in.</small>
                        </div>
                        <div>
                            <label for="customerPhone">Phone</label>
                            <input type="tel" id="customerPhone" name="phone" maxlength="50" autocomplete="tel">
                        </div>
                    </div>

                    <div>
                        <label for="customerAddress1">Address</label>
                        <input type="text" id="customerAddress1" name="address_1" maxlength="255" autocomplete="address-line1">
                    </div>

                    <div>
                        <label for="customerAddress2">Address 2</label>
                        <input type="text" id="customerAddress2" name="address_2" maxlength="255" autocomplete="address-line2">
                    </div>

                    <div class="customer-profile__form-grid customer-profile__form-grid--three">
                        <div>
                            <label for="customerCity">City</label>
                            <input type="text" id="customerCity" name="city" maxlength="100" autocomplete="address-level2">
                        </div>
                        <div>
                            <label for="customerStateProvince">State/Province</label>
                            <select id="customerStateProvince" name="state_prov_id" autocomplete="address-level1" required></select>
                        </div>
                        <div>
                            <label for="customerPostalCode">Postal code</label>
                            <input type="text" id="customerPostalCode" name="postal_code" maxlength="25" autocomplete="postal-code">
                        </div>
                    </div>

                    <div>
                        <label for="customerCountry">Country</label>
                        <input type="text" id="customerCountry" name="country" maxlength="100" autocomplete="country-name">
                    </div>

                    <div class="customer-profile__edit-actions">
                        <button type="submit" class="customer-profile__add-button customer-profile__save-button">Save changes</button>
                        <button type="button" class="customer-profile__cancel-button" id="customerProfileCancelEdit">Cancel</button>
                    </div>
                </form>
            </article>

            <article class="customer-profile__panel customer-profile__stats" aria-label="Account summary">
                <div>
                    <strong id="customerOrderCount">0</strong>
                    <span>Orders</span>
                </div>
                <div>
                    <strong id="customerFavoriteCount">0</strong>
                    <span>Favorites</span>
                </div>
                <div>
                    <strong id="customerMemberSince">—</strong>
                    <span>Member since</span>
                </div>
            </article>
        </div>

        <article class="customer-profile__panel customer-profile__section">
            <div class="customer-profile__panel-heading">
                <div>
                    <p class="customer-profile__eyebrow">Your travel shortlist</p>
                    <h2>Favorites</h2>
                </div>
            </div>

            <form id="customerFavoriteForm" class="customer-profile__favorite-form">
                <div>
                    <label for="customerFavoriteType">Category</label>
                    <select id="customerFavoriteType" name="favorite_type" required>
                        <option value="cruise_line">Cruise line</option>
                        <option value="ship">Ship</option>
                        <option value="destination">Destination</option>
                        <option value="itinerary">Itinerary</option>
                        <option value="port">Port</option>
                        <option value="excursion">Shore excursion</option>
                    </select>
                </div>
                <div class="customer-profile__favorite-picker">
                    <label for="customerFavoriteEntity">Item</label>
                    <select id="customerFavoriteEntity" name="entity_id" required></select>
                </div>
                <button type="submit" class="btn customer-profile__add-button">Add favorite</button>
            </form>

            <div id="customerFavoritesList" class="customer-profile__favorites-list"></div>
        </article>

        <article class="customer-profile__panel customer-profile__section" id="customerNewsPreferences">
            <div class="customer-profile__panel-heading">
                <div>
                    <p class="customer-profile__eyebrow">Personalized travel updates</p>
                    <h2>News Preferences</h2>
                    <p>Follow the cruise lines, resorts, destinations, categories, and keywords that matter to you.</p>
                </div>
            </div>

            <div id="customerNewsAlert" class="customer-profile__alert" role="status" aria-live="polite" hidden></div>

            <form id="newsPreferenceForm" class="customer-profile__favorite-form">
                <div>
                    <label for="customerNewsPreferenceType">Interest type</label>
                    <select id="customerNewsPreferenceType" name="preference_type">
                        <option value="entity">Cruise line, ship, resort, port, or destination</option>
                        <option value="category">News category</option>
                        <option value="keyword">Keyword</option>
                    </select>
                </div>
                <div class="customer-profile__favorite-picker">
                    <label for="customerNewsPreferenceSelection">Interest</label>
                    <select id="customerNewsPreferenceSelection" name="selection" required></select>
                </div>
                <div>
                    <label for="customerNewsPriority">Priority</label>
                    <select id="customerNewsPriority" name="priority_level">
                        <option value="1">Normal</option>
                        <option value="2">High</option>
                        <option value="3">Essential</option>
                    </select>
                </div>
                <button type="submit" class="customer-profile__add-button">Follow interest</button>
            </form>

            <section class="customer-profile__news-results" aria-labelledby="customerSelectedNewsTitle">
                <div class="customer-profile__panel-heading">
                    <div>
                        <h3 id="customerSelectedNewsTitle">News for this interest</h3>
                        <p id="newsPreferencePreviewStatus">Select an interest to see matching published stories.</p>
                    </div>
                </div>
                <div id="newsPreferencePreview" class="customer-news-grid"></div>
            </section>

            <div class="customer-profile__news-preference-grid">
                <section aria-labelledby="customerFollowedNewsTitle">
                    <h3 id="customerFollowedNewsTitle">Followed interests</h3>
                    <div id="newsPreferenceList" class="customer-profile__favorites-list"></div>
                </section>

                <form id="newsPrivacyForm" class="customer-profile__news-settings">
                    <h3>Personalization and privacy</h3>
                    <label class="customer-profile__checkbox">
                        <input type="checkbox" name="personalization_enabled" value="1">
                        Use my preferences for recommendations
                    </label>
                    <label class="customer-profile__checkbox">
                        <input type="checkbox" name="history_enabled" value="1">
                        Use my news viewing history
                    </label>
                    <label for="customerNewsFocus">News focus</label>
                    <select id="customerNewsFocus" name="news_type_preference">
                        <option value="both">Cruise &amp; resort</option>
                        <option value="cruise">Cruise</option>
                        <option value="resort">Resort</option>
                    </select>
                    <div class="customer-profile__edit-actions">
                        <button type="submit" class="customer-profile__add-button">Save settings</button>
                        <button type="button" class="customer-profile__cancel-button" id="clearNewsHistory">Delete news-view history</button>
                    </div>
                </form>
            </div>

            <form id="newsAlertForm" class="customer-profile__favorite-form customer-profile__news-digest">
                <div>
                    <label for="customerNewsAlertName">Digest name</label>
                    <input id="customerNewsAlertName" name="alert_name" value="My travel news" maxlength="180">
                </div>
                <div>
                    <label for="customerNewsFrequency">Email digest frequency</label>
                    <select id="customerNewsFrequency" name="frequency">
                        <option value="weekly">Weekly</option>
                        <option value="daily">Daily</option>
                        <option value="monthly">Monthly</option>
                        <option value="breaking">Breaking news only</option>
                        <option value="disabled">Disabled</option>
                    </select>
                </div>
                <button type="submit" class="customer-profile__add-button">Create digest</button>
            </form>
            <div id="newsAlertList" class="customer-profile__orders"></div>
        </article>

        <article class="customer-profile__panel customer-profile__section">
            <div class="customer-profile__panel-heading">
                <div>
                    <p class="customer-profile__eyebrow">Store activity</p>
                    <h2>Purchase History</h2>
                </div>
            </div>
            <div id="customerOrdersList" class="customer-profile__orders"></div>
        </article>
    </div>
</section>
