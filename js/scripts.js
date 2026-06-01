document.addEventListener('DOMContentLoaded', function() {
    const wrapper = document.querySelector('.form-container-wrapper');
    const loginBtn = document.querySelector('.overlay-content:last-child .overlay-btn');
    const registerBtn = document.querySelector('.overlay-content:first-child .overlay-btn');

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
});