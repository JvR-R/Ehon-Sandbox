// File: /vmi/js/script.js

document.addEventListener('DOMContentLoaded', function() {
    // 1. Handle burger menu
    var burgerMenu = document.querySelector('.burger-menu');
    var mobileMenu = document.getElementById('mobileMenu');
    var menuOverlay = document.getElementById('menuOverlay');
    var mobileMenuClose = document.getElementById('mobileMenuClose');
    
    if (burgerMenu) {
      burgerMenu.addEventListener('click', function() {
        toggleMobileMenu();
      });
    }
    
    if (mobileMenuClose) {
      mobileMenuClose.addEventListener('click', function() {
        closeMobileMenu();
      });
    }
    
    if (menuOverlay) {
      menuOverlay.addEventListener('click', function() {
        closeMobileMenu();
      });
    }
    
    function toggleMobileMenu() {
      burgerMenu.classList.toggle('active');
      mobileMenu.classList.toggle('active');
      menuOverlay.classList.toggle('active');
      document.body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
    }
    
    function closeMobileMenu() {
      burgerMenu.classList.remove('active');
      mobileMenu.classList.remove('active');
      menuOverlay.classList.remove('active');
      document.body.style.overflow = '';
    }
    
    // Handle mobile menu submenus
    var mobileSubmenus = document.querySelectorAll('.mobile-menu-item.has-submenu > a');
    mobileSubmenus.forEach(function(submenu) {
      submenu.addEventListener('click', function(event) {
        event.preventDefault();
        var parent = this.parentElement;
        parent.classList.toggle('expanded');
      });
    });

    // 2. Handle desktop submenus
    var submenus = document.querySelectorAll('.has-submenu > a');
    submenus.forEach(function(submenu) {
      submenu.addEventListener('click', function(event) {
        event.preventDefault();
        var next = this.nextElementSibling;
        if (next) {
          next.style.display = (next.style.display === 'block') ? 'none' : 'block';
        }
      });
    });
  
    // 3. Check URL params for errors (example)
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('error') && urlParams.get('error') === 'invalid_email') {
      toastr.error('Invalid email. Please use a valid email or contact support.');
    }
  
    // 4. Google SSO
    var googleButton = document.querySelector('.login-button.google');
    if (googleButton) {
      googleButton.addEventListener('click', function(e) {
        e.preventDefault();
        handleGoogleLogin();
      });
    }
  
    function handleGoogleLogin() {
      console.log(window.google?.accounts?.id); 
      // Replace with your actual client ID
      google.accounts.id.initialize({
        client_id: '856620376784-6oso2q1m27hk5huc1l78b6379j43q4vb.apps.googleusercontent.com',
        callback: handleCredentialResponse
      });
      // google.accounts.id.prompt();
      google.accounts.id.renderButton(
        document.querySelector('.login-button.google'),
        { theme: 'outline', size: 'large' } // or any config
      );
    }
  
    function handleCredentialResponse(response) {
      if (!response || !response.credential) {
        toastr.error('Invalid credential response from Google.');
        console.error('Google SSO Error: Invalid credential response', response);
        return;
      }
  
      // Detect dark mode preference
      function detectDarkMode() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
          return 1; // Dark mode
        }
        return 0; // Light mode
      }
  
      // Send ID token and dark mode preference to your backend
      $.post("/vmi/login/callback.php", { 
        credential: response.credential,
        dark_mode: detectDarkMode()
      })
        .done(function(data) {
          try {
            var jsonResponse = (typeof data === 'string')
              ? JSON.parse(data) : data;
  
            if (jsonResponse.success) {
              window.location.href = '/vmi/reports';
            } else {
              toastr.error(jsonResponse.message || 'An error occurred during Google login.');
              console.error('Google SSO Backend Error:', jsonResponse);
            }
          } catch (e) {
            toastr.error('Unexpected response from server.');
            console.error('Error parsing response:', e);
          }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
          toastr.error('Failed to send request to server: ' + errorThrown);
          console.error('AJAX Request Failed:', textStatus, errorThrown);
        });
    }
  
    // 5. Microsoft SSO
    var microsoftButton = document.querySelector('.login-button.microsoft');
    if (microsoftButton) {
      microsoftButton.addEventListener('click', function(e) {
        e.preventDefault();
        // Redirect to Microsoft login handler (adjust path if needed)
        window.location.href = '/vmi/login/microsoft_login.php';
      });
    }

    // 6. Detect and set dark mode preference before form submission
    var loginForm = document.getElementById('loginForm');
    var darkModeInput = document.getElementById('dark_mode');
    
    if (loginForm && darkModeInput) {
      // Detect browser dark mode preference
      function detectDarkMode() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
          console.log('Dark mode detected: YES');
          return 1; // Dark mode
        }
        console.log('Dark mode detected: NO');
        return 0; // Light mode
      }
      
      // Set dark mode value on page load
      var detectedValue = detectDarkMode();
      darkModeInput.value = detectedValue;
      console.log('Dark mode input set to:', detectedValue);
      
      // Update before form submission (in case user changes system preference)
      loginForm.addEventListener('submit', function(e) {
        var finalValue = detectDarkMode();
        darkModeInput.value = finalValue;
        console.log('Form submitting with dark_mode:', finalValue);
      });
    } else {
      console.warn('Login form or dark_mode input not found');
    }
  });
  