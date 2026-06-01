document.addEventListener('DOMContentLoaded', function() {
    const wrapper = document.querySelector('.form-container-wrapper');
    const loginBtn = document.querySelector('#loginTrigger');
    const registerBtn = document.querySelector('#registerTrigger');
    const goToRegister = document.querySelector('#goToRegister');
    const goToLogin = document.querySelector('#goToLogin');

    // Function to switch to register mode
    function registerMode() {
        wrapper.classList.add('register-mode');
    }

    // Function to switch to login mode
    function loginMode() {
        wrapper.classList.remove('register-mode');
    }

    // Add click event listeners to the buttons
    if (registerBtn) registerBtn.addEventListener('click', registerMode);
    if (loginBtn) loginBtn.addEventListener('click', loginMode);
    
    // Add click event listeners to the links
    if (goToRegister) goToRegister.addEventListener('click', (e) => { 
        e.preventDefault(); // Prevent link from jumping
        registerMode(); 
    });
    if (goToLogin) goToLogin.addEventListener('click', (e) => { 
        e.preventDefault(); // Prevent link from jumping
        loginMode(); 
    });

    // Check for registration success flag in URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('registered')) {
        // If user just registered, show the login form
        loginMode();
    }
});