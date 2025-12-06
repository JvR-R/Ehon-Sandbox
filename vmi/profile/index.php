<?php
require_once __DIR__ . '/../db/pdo_boot.php';
require_once __DIR__ . '/../db/log.php';
require_once __DIR__ . '/../db/border2.php';

// Get user information
$userId = (int)($_SESSION['userId'] ?? 0);
$username = $_SESSION['username'] ?? '';

// Get user's full details from database
$userFirstName = '';
$userLastName = '';
if ($userId > 0) {
    $stmt = $pdo->prepare('SELECT name, last_name FROM login WHERE user_id = :uid');
    $stmt->execute(['uid' => $userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($userData) {
        $userFirstName = $userData['name'] ?? '';
        $userLastName = $userData['last_name'] ?? '';
    }
}
$fullName = trim($userFirstName . ' ' . $userLastName) ?: '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - EHON VMI</title>
    <!-- THEME INIT - Must be BEFORE theme.css for automatic browser dark mode detection -->
    <script src="/vmi/js/theme-init.js"></script>
    <!-- THEME CSS - MUST BE FIRST -->
    <link rel="stylesheet" href="/vmi/css/theme.css">
    <!-- Other CSS files -->
    <link rel="stylesheet" href="/vmi/css/style_rep.css">
    <link rel="stylesheet" href="style.css">
    <!-- Toastr for notifications -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css">
</head>
<body>
    <main class="profile-main">
        <div class="profile-container">
            <h1 class="profile-title">Profile Settings</h1>

            <!-- Personal Information Section -->
            <div class="profile-section">
                <div class="profile-section-header">
                    <span class="profile-section-icon">👤</span>
                    <h2 class="profile-section-title">Personal Information</h2>
                </div>
                <form id="profileForm" class="profile-form">
                    <div class="profile-form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?=esc($username)?>" readonly>
                        <span class="profile-field-note">Username cannot be changed</span>
                    </div>
                    <div class="profile-form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?=esc($username)?>" readonly>
                        <span class="profile-field-note">Email cannot be changed</span>
                    </div>
                    <div class="profile-form-group">
                        <label for="fullName">Full Name</label>
                        <input type="text" id="fullName" name="fullName" value="<?=esc($fullName)?>" placeholder="Enter your full name">
                    </div>
                    <div class="profile-form-actions">
                        <button type="submit" class="profile-btn profile-btn-primary">
                            <span class="profile-btn-icon">👤</span>
                            UPDATE PROFILE
                        </button>
                    </div>
                </form>
            </div>

            <!-- Change Password Section -->
            <div class="profile-section">
                <div class="profile-section-header">
                    <span class="profile-section-icon">🔒</span>
                    <h2 class="profile-section-title">Change Password</h2>
                </div>
                <form id="passwordForm" class="profile-form">
                    <div class="profile-form-group">
                        <label for="currentPassword">Current Password</label>
                        <div class="profile-password-wrapper">
                            <input type="password" id="currentPassword" name="currentPassword" placeholder="Enter current password">
                            <button type="button" class="profile-password-toggle" data-target="currentPassword" aria-label="Toggle password visibility">
                                <span class="profile-eye-icon">👁️</span>
                            </button>
                        </div>
                    </div>
                    <div class="profile-form-group">
                        <label for="newPassword">New Password</label>
                        <div class="profile-password-wrapper">
                            <input type="password" id="newPassword" name="newPassword" placeholder="Enter new password">
                            <button type="button" class="profile-password-toggle" data-target="newPassword" aria-label="Toggle password visibility">
                                <span class="profile-eye-icon">👁️</span>
                            </button>
                        </div>
                        <span class="profile-field-note">Leave blank to keep current password</span>
                    </div>
                    <div class="profile-form-group">
                        <label for="confirmPassword">Confirm New Password</label>
                        <div class="profile-password-wrapper">
                            <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm new password">
                            <button type="button" class="profile-password-toggle" data-target="confirmPassword" aria-label="Toggle password visibility">
                                <span class="profile-eye-icon">👁️</span>
                            </button>
                        </div>
                    </div>
                    <div class="profile-form-actions">
                        <button type="submit" class="profile-btn profile-btn-secondary" id="updatePasswordBtn" disabled>
                            <span class="profile-btn-icon">🔒</span>
                            UPDATE PASSWORD
                        </button>
                    </div>
                </form>
            </div>

            <!-- Appearance Section -->
            <div class="profile-section">
                <div class="profile-section-header">
                    <span class="profile-section-icon">⚙️</span>
                    <h2 class="profile-section-title">Appearance</h2>
                </div>
                <div class="profile-form">
                    <div class="profile-form-group">
                        <label for="darkTheme">Dark Theme</label>
                        <div class="profile-toggle-wrapper">
                            <span class="profile-toggle-description">Toggle between light and dark mode</span>
                            <div class="profile-theme-toggle">
                                <input type="checkbox" id="darkTheme" class="profile-theme-checkbox">
                                <label for="darkTheme" class="profile-theme-label">
                                    <span class="profile-theme-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="profile-form-group">
                        <label for="tankViewMode">Tank View Mode</label>
                        <div class="profile-toggle-wrapper">
                            <span class="profile-toggle-description">Display style for tank list</span>
                            <div class="profile-select-wrapper">
                                <select id="tankViewMode" class="profile-select">
                                    <option value="auto">Automatic</option>
                                    <option value="card">Card View</option>
                                    <option value="table">Table View</option>
                                </select>
                            </div>
                        </div>
                        <span class="profile-field-note">Automatic: cards for ≤5 tanks, table for more</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>
    <script>
    toastr.options = {
        "closeButton": true,
        "newestOnTop": true,
        "positionClass": "toast-top-right",
        "timeOut": "5000"
    };
    </script>
    <script src="/vmi/js/theme-toggle.js"></script>
    <script src="script.js"></script>
    <!-- Note: user-menu.js is already loaded by border2.php -->
</body>
</html>

