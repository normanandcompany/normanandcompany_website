<!-- ========================================= -->
<!-- USER MANAGEMENT PAGE -->
<!-- File: /admin/pages/users.php -->
<!-- Copyright 2026 @ Norman & Company -->
<!-- Written by: Joe Leone -->
<!-- ========================================= -->

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

requireRole('admin');
?>

<section class="product-manager user-manager">

    <div id="page-title-meta"
        data-title="Norman and Company | User Management"
        style="display: none;">
    </div>

    <div class="product-manager-header">
        <div>
            <h1>User Management</h1>
        </div>

        <button type="button" class="btn-primary product-add-button" id="addUserBtn">
            Add User
        </button>
    </div>

    <div class="product-toolbar" aria-label="User filters">
        <div class="product-search-field">
            <label for="userSearchInput">Search</label>
            <input
                type="search"
                id="userSearchInput"
                placeholder="Name, email, city, or phone"
                autocomplete="off"
            >
        </div>

        <div class="product-filter-field">
            <label for="userRoleFilter">Role</label>
            <select id="userRoleFilter">
                <option value="all">All Roles</option>
            </select>
        </div>

        <button type="button" class="btn-secondary product-filter-reset" id="resetUserFiltersBtn">
            Reset
        </button>
    </div>

    <div class="product-metrics" aria-label="User summary">
        <div class="product-metric">
            <span id="userTotalCount">0</span>
            <small>Total</small>
        </div>
        <div class="product-metric">
            <span id="userActiveCount">0</span>
            <small>Active</small>
        </div>
        <div class="product-metric">
            <span id="userFilteredCount">0</span>
            <small>Shown</small>
        </div>
    </div>

    <div id="userAlert" class="product-alert" role="status" aria-live="polite" hidden></div>

    <div class="product-table-panel">
        <div class="product-table-scroll">
            <table class="product-table user-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Phone</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <tr>
                        <td colspan="8" class="product-empty-state">Loading users...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="product-pagination">
            <button type="button" id="userPrevPageBtn" class="btn-secondary">
                Previous
            </button>

            <span id="userPageInfo">
                Page 1
            </span>

            <button type="button" id="userNextPageBtn" class="btn-secondary">
                Next
            </button>
        </div>
    </div>

    <dialog id="userFormDialog" class="product-dialog user-dialog">
        <form id="userForm" class="product-form">
            <input type="hidden" id="userId" name="id">

            <div class="product-dialog-header">
                <div>
                    <p class="section-kicker">Account Profile</p>
                    <h2 id="userFormTitle">Add User</h2>
                </div>

                <button type="button" class="product-dialog-close" id="closeUserDialogBtn" aria-label="Close">
                    &times;
                </button>
            </div>

            <div class="product-form-grid user-form-grid">
                <div class="product-form-main">
                    <div class="product-field-row two-column">
                        <div class="form-group stacked">
                            <label for="userFirstName">First Name</label>
                            <input
                                type="text"
                                id="userFirstName"
                                name="first_name"
                                maxlength="100"
                                required
                            >
                        </div>

                        <div class="form-group stacked">
                            <label for="userLastName">Last Name</label>
                            <input
                                type="text"
                                id="userLastName"
                                name="last_name"
                                maxlength="100"
                                required
                            >
                        </div>
                    </div>

                    <div class="product-field-row two-column">
                        <div class="form-group stacked">
                            <label for="userEmailAddress">Email</label>
                            <input
                                type="email"
                                id="userEmailAddress"
                                name="email_address"
                                maxlength="255"
                                required
                            >
                        </div>

                        <div class="form-group stacked">
                            <label for="userPhone">Phone</label>
                            <input
                                type="tel"
                                id="userPhone"
                                name="phone"
                                maxlength="50"
                            >
                        </div>
                    </div>

                    <div class="product-field-row two-column">
                        <div class="form-group stacked">
                            <label for="userRoleId">Role</label>
                            <select id="userRoleId" name="user_role_id" required>
                                <option value="">Select role</option>
                            </select>
                        </div>

                        <div class="form-group stacked">
                            <label for="userPassword">Password</label>
                            <input
                                type="password"
                                id="userPassword"
                                name="password"
                                minlength="8"
                                autocomplete="new-password"
                            >
                        </div>
                    </div>

                    <div class="form-group stacked">
                        <label for="userAddress1">Address</label>
                        <input
                            type="text"
                            id="userAddress1"
                            name="address_1"
                            maxlength="255"
                        >
                    </div>

                    <div class="form-group stacked">
                        <label for="userAddress2">Address 2</label>
                        <input
                            type="text"
                            id="userAddress2"
                            name="address_2"
                            maxlength="255"
                        >
                    </div>

                    <div class="product-field-row three-column">
                        <div class="form-group stacked">
                            <label for="userCity">City</label>
                            <input
                                type="text"
                                id="userCity"
                                name="city"
                                maxlength="100"
                            >
                        </div>

                        <div class="form-group stacked">
                            <label for="userStateProvId">State/Province</label>
                            <select id="userStateProvId" name="state_prov_id" required>
                                <option value="">Select state</option>
                            </select>
                        </div>

                        <div class="form-group stacked">
                            <label for="userPostalCode">Postal Code</label>
                            <input
                                type="text"
                                id="userPostalCode"
                                name="postal_code"
                                maxlength="25"
                            >
                        </div>
                    </div>

                    <div class="form-group stacked">
                        <label for="userCountry">Country</label>
                        <input
                            type="text"
                            id="userCountry"
                            name="country"
                            maxlength="100"
                        >
                    </div>
                </div>

                <aside class="product-form-side">
                    <div class="user-profile-card">
                        <div class="user-avatar-preview" id="userAvatarPreview">NC</div>
                        <strong id="userPreviewName">New User</strong>
                        <span id="userPreviewEmail">No email entered</span>
                    </div>

                    <div class="product-toggle-grid" aria-label="User flags">
                        <label class="toggle-row">
                            <input type="checkbox" id="userVisible" name="visible" value="1" checked>
                            <span>Visible</span>
                        </label>

                        <label class="toggle-row">
                            <input type="checkbox" id="userActive" name="is_active" value="1" checked>
                            <span>Active</span>
                        </label>
                    </div>
                </aside>
            </div>

            <div class="product-dialog-actions">
                <button type="button" class="btn-secondary" id="cancelUserBtn">
                    Cancel
                </button>

                <button type="submit" class="btn-primary" id="saveUserBtn">
                    Save User
                </button>
            </div>
        </form>
    </dialog>
</section>
