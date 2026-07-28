<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

requireRole('admin');
?>

<section class="content-section">

    <!-- Page Title Metadata -->
    <div id="page-title-meta"
        data-title="Norman and Company | Add New User"
        style="display: none;">
    </div>

    <!-- PAGE TITLE -->

    <h1>Add New User</h1>

    <!-- ADD NEW USER CARD -->

    <div class="card">
        <form id="addnewuserForm">

            <!-- USER NAME -->

            <div class="form-group">
                <label for="userfirstName">
                    First Name:
                </label>
                <input
                    type="text"
                    id="userfirstName"
                    name="userfirstName"
                    placeholder="Enter First Name"
                    size="20"
                    required
                >
                <label for="userlastName">
                    Last Name:
                </label>
                <input
                    type="text"
                    id="userlastName"
                    name="userlastName"
                    placeholder="Enter Last Name"
                    size="20"
                    required
                >
                <label for="userEmail">
                    Email:
                </label>
                <input
                    type="email"
                    id="userEmail"
                    name="userEmail"
                    placeholder="Enter Email"
                    size="50"
                    required
                >
                <label for="userPassword">
                    Password:
                </label>
                <input 
                    type="password" 
                    id="userPassword"
                    name="userPassword"
                    placeholder="Enter password" 
                    size="20"
                    required
                >
                <!-- <span id="togglePassword" 
                style="cursor: pointer; font-size: 22px; padding: 5px;">
                👁️
                </span> -->
            </div>

            <!-- USER TYPE -->

            <div class="form-group">
                <label for="userType">
                    User Type:
                </label>
                <select
                    id="userType"
                    name="userType"
                    required
                >
                    <option value="">
                        Select the user type
                    </option>

                    <option value="Administrator">
                        Administrator
                    </option>
                    <option value="Customer">
                        Customer
                    </option>
                </select>
            </div>

            <!-- USER ADDRESS INFORMATION -->

            <div class="form-group">
                <label for="userAddress1">
                    Address:
                </label>
                <input
                    type="text"
                    id="userAddress1"
                    name="userAddress1"
                    placeholder="Address information"
                    size="20"
                    required
                >
                <label for="userAddress2">
                    Address2:
                </label>
                <input
                    type="text"
                    id="userAddress2"
                    name="userAddress2"
                    placeholder=""
                    size="20"
                >
                <label for="userCity">
                    City:
                </label>
                <input
                    type="text"
                    id="userCity"
                    name="userCity"
                    placeholder="City"
                    size="20"
                    required
                >
                <label for="userState">
                    State:
                </label>
                <input
                    type="text"
                    id="userState"
                    name="userState"
                    placeholder="State"
                    size="2"
                    required
                >
                <label for="userZip">
                    Zip Code:
                </label>
                <input
                    type="text"
                    id="userZip"
                    name="userZip"
                    placeholder="Zip Code"
                    size="20"
                    required
                >
                <label for="userCountry">
                    Country:
                </label>
                <input
                    type="text"
                    id="userCountry"
                    name="userCountry"
                    placeholder="Country"
                    size="20"
                >
            </div>    

            <!-- SUBMIT BUTTON -->

            <div class="form-actions">
                <button
                    type="submit"
                    class="btn-primary"
                >
                    Submit
                </button>

            </div>
        </form>

    </div>

</section>

<!-- <script>
    const passwordInput = document.getElementById('userPassword');
    const toggleIcon = document.getElementById('togglePassword');

    if (!passwordInput || !toggleIcon) {
        console.error("Elements not found!");
    } else {

        toggleIcon.addEventListener('click', function () {

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleIcon.textContent = '👁️';
            }

        });

    }
</script> -->