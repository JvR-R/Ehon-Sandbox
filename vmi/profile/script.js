/**
 * Profile Settings Page JavaScript
 */

(function() {
  'use strict';

  // Helper function for toastr notifications with fallback
  const showToast = function(message, type = 'success') {
    if (typeof toastr !== 'undefined') {
      toastr[type](message);
    } else {
      alert(message);
    }
  };

  const ProfileSettings = {
    init: function() {
      this.setupProfileForm();
      this.setupPasswordForm();
      this.setupPasswordToggles();
      this.setupThemeToggle();
      this.setupTankViewMode();
      this.validatePasswordForm();
    },

    setupProfileForm: function() {
      const form = document.getElementById('profileForm');
      if (!form) return;

      form.addEventListener('submit', function(e) {
        e.preventDefault();
        ProfileSettings.updateProfile();
      });
    },

    updateProfile: function() {
      const form = document.getElementById('profileForm');
      const fullName = document.getElementById('fullName').value.trim();
      const nameParts = fullName.split(' ');
      const firstName = nameParts[0] || '';
      const lastName = nameParts.slice(1).join(' ') || '';

      if (!firstName) {
        showToast('Full name is required', 'error');
        return;
      }

      const submitBtn = form.querySelector('.profile-btn-primary');
      const originalText = submitBtn.textContent.trim();
      submitBtn.textContent = 'UPDATING...';
      submitBtn.disabled = true;

      fetch('/vmi/db/update_name.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `firstName=${encodeURIComponent(firstName)}&lastName=${encodeURIComponent(lastName)}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showToast('Profile updated successfully', 'success');
        } else {
          showToast(data.message || 'Failed to update profile', 'error');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while updating your profile', 'error');
      })
      .finally(() => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
      });
    },

    setupPasswordForm: function() {
      const form = document.getElementById('passwordForm');
      if (!form) return;

      form.addEventListener('submit', function(e) {
        e.preventDefault();
        ProfileSettings.updatePassword();
      });
    },

    updatePassword: function() {
      const currentPassword = document.getElementById('currentPassword').value;
      const newPassword = document.getElementById('newPassword').value;
      const confirmPassword = document.getElementById('confirmPassword').value;

      if (!currentPassword) {
        showToast('Current password is required', 'error');
        return;
      }

      if (newPassword && newPassword !== confirmPassword) {
        showToast('New passwords do not match', 'error');
        return;
      }

      if (newPassword && newPassword.length < 6) {
        showToast('New password must be at least 6 characters long', 'error');
        return;
      }

      const submitBtn = document.getElementById('updatePasswordBtn');
      const originalText = submitBtn.textContent.trim();
      submitBtn.textContent = 'UPDATING...';
      submitBtn.disabled = true;

      fetch('/vmi/profile/update_password.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `currentPassword=${encodeURIComponent(currentPassword)}&newPassword=${encodeURIComponent(newPassword)}&confirmPassword=${encodeURIComponent(confirmPassword)}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Clear form
          document.getElementById('passwordForm').reset();
          document.getElementById('updatePasswordBtn').disabled = true;
          
          showToast('Password updated successfully', 'success');
        } else {
          showToast(data.message || 'Failed to update password', 'error');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while updating your password', 'error');
      })
      .finally(() => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
      });
    },

    setupPasswordToggles: function() {
      const toggles = document.querySelectorAll('.profile-password-toggle');
      toggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
          const targetId = this.getAttribute('data-target');
          const input = document.getElementById(targetId);
          if (input) {
            if (input.type === 'password') {
              input.type = 'text';
              this.querySelector('.profile-eye-icon').textContent = '🙈';
            } else {
              input.type = 'password';
              this.querySelector('.profile-eye-icon').textContent = '👁️';
            }
          }
        });
      });
    },

    setupThemeToggle: function() {
      const checkbox = document.getElementById('darkTheme');
      if (!checkbox) return;

      // Set initial state based on current theme
      const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
      checkbox.checked = isDark;

      // Listen for theme changes from other sources
      window.addEventListener('themeChanged', function(e) {
        checkbox.checked = e.detail.theme === 'dark';
      });

      // Handle toggle
      checkbox.addEventListener('change', function() {
        if (window.ThemeManager) {
          window.ThemeManager.toggleTheme();
        }
      });
    },

    setupTankViewMode: function() {
      const select = document.getElementById('tankViewMode');
      if (!select) return;

      // Set initial state based on stored preference
      const storedPreference = localStorage.getItem('tankViewMode') || 'auto';
      select.value = storedPreference;

      // Handle change
      select.addEventListener('change', function() {
        const value = this.value;
        localStorage.setItem('tankViewMode', value);
        
        showToast('Tank view preference saved. Refresh the clients page to see changes.', 'success');
      });
    },

    validatePasswordForm: function() {
      const form = document.getElementById('passwordForm');
      const updateBtn = document.getElementById('updatePasswordBtn');
      if (!form || !updateBtn) return;

      const inputs = form.querySelectorAll('input[type="password"]');
      inputs.forEach(input => {
        input.addEventListener('input', function() {
          const currentPassword = document.getElementById('currentPassword').value;
          const newPassword = document.getElementById('newPassword').value;
          const confirmPassword = document.getElementById('confirmPassword').value;

          // Enable button only if current password is filled and new passwords match
          if (currentPassword && newPassword && confirmPassword && newPassword === confirmPassword) {
            updateBtn.disabled = false;
          } else {
            updateBtn.disabled = true;
          }
        });
      });
    }
  };

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      ProfileSettings.init();
    });
  } else {
    ProfileSettings.init();
  }

  // Expose for external use
  window.ProfileSettings = ProfileSettings;

})();

