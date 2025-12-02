<?php
// footer.php
?>
        <footer>
            <div class="footer-logo">Muffeia</div>
            <p>Creating a safer, more compassionate online community for emotional expression and support.</p>
            
            <div class="footer-links">
                <a href="about.php">About Us</a>
                <a href="privacy.php">Privacy Policy</a>
                <a href="guidelines.php">Community Guidelines</a>
                <a href="resources.php">Mental Health Resources</a>
                <a href="contact.php">Contact</a>
            </div>
            
            <p class="copyright">© 2025 Muffeia. All rights reserved. Built with security and compassion in mind.</p>
        </footer>
    </div>

    <!-- Login and Register Modals -->
    <div class="modal" id="loginModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Welcome Back</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <?php if ($success): ?>
                    <div class="success-message"><?php echo $success; ?></div>
                <?php elseif ($error && !$is_register_mode): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                <form id="loginForm" method="POST" action="">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="form-group">
                        <label class="form-label" for="loginEmail">Email</label>
                        <input type="email" id="loginEmail" name="email" class="form-input" placeholder="Enter your email" value="<?php echo $preserved_email; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="loginPassword">Password</label>
                        <div style="position: relative;">
                            <input type="password" id="loginPassword" name="password" class="form-input" placeholder="Enter your password" required>
                            <i class="fas fa-eye password-toggle" data-target="loginPassword"></i>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px;">Sign In</button>
                </form>
                <div class="form-footer">
                    <p>Don't have an account? <a href="#" id="switchToRegister">Sign up here</a></p>
                    <p><a href="../auth/forgot_password.php">Forgot your password?</a></p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="registerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Join Muffeia</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <?php if ($error && $is_register_mode): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                <form id="registerForm" method="POST" action="">
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="form-group">
                        <label class="form-label" for="registerUsername">Username</label>
                        <input type="text" id="registerUsername" name="username" class="form-input" placeholder="e.g., Happy_User" pattern="[a-zA-Z0-9_ ]{3,30}" title="Username can contain letters, numbers, underscores, and spaces (3-30 characters)" value="<?php echo $preserved_username; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="registerEmail">Email</label>
                        <input type="email" id="registerEmail" name="email" class="form-input" placeholder="Enter your email" value="<?php echo $preserved_email; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="registerPassword">Password</label>
                        <div style="position: relative;">
                            <input type="password" id="registerPassword" name="password" class="form-input" placeholder="Create a strong password" required>
                            <i class="fas fa-eye password-toggle" data-target="registerPassword"></i>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar">
                                <div class="strength-bar-fill weak"></div>
                            </div>
                            <div class="strength-text weak">Weak Password</div>
                            <ul class="strength-requirements">
                                <li class="unmet"><i class="far fa-circle"></i> At least 8 characters</li>
                                <li class="unmet"><i class="far fa-circle"></i> One uppercase letter</li>
                                <li class="unmet"><i class="far fa-circle"></i> One lowercase letter</li>
                                <li class="unmet"><i class="far fa-circle"></i> One number</li>
                                <li class="unmet"><i class="far fa-circle"></i> One special character</li>
                            </ul>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px;" disabled>Create Account</button>
                </form>
                <div class="form-footer">
                    <p>Already have an account? <a href="#" id="switchToLogin">Sign in here</a></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Modal functionality
        const loginModal = document.getElementById('loginModal');
        const registerModal = document.getElementById('registerModal');
        const loginBtn = document.getElementById('loginBtn');
        const registerBtn = document.getElementById('registerBtn');
        const closeModalBtns = document.querySelectorAll('.close-modal');
        const switchToRegister = document.getElementById('switchToRegister');
        const switchToLogin = document.getElementById('switchToLogin');

        function openModal(modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        document.getElementById('loginBtn').addEventListener('click', function() {
            window.location.href = '../auth/login.php'; // Redirect to login page
        });
        document.getElementById('contact').addEventListener('click', function() {
            window.location.href = 'contact.php'; // Redirect to login page
        });

        closeModalBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                closeModal(loginModal);
                closeModal(registerModal);
            });
        });

        switchToRegister.addEventListener('click', (e) => {
            e.preventDefault();
            closeModal(loginModal);
            openModal(registerModal);
        });

        switchToLogin.addEventListener('click', (e) => {
            e.preventDefault();
            closeModal(registerModal);
            openModal(loginModal);
        });

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === loginModal) closeModal(loginModal);
            if (e.target === registerModal) closeModal(registerModal);
        });

        // Mobile menu toggle
        const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
        const nav = document.querySelector('nav ul');
        
        mobileMenuBtn.addEventListener('click', () => {
            nav.style.display = nav.style.display === 'flex' ? 'none' : 'flex';
        });
        
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                nav.style.display = 'flex';
            } else {
                nav.style.display = 'none';
            }
        });

        // Password toggle functionality
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('password-toggle')) {
                const targetId = e.target.dataset.target;
                const input = document.getElementById(targetId);
                
                if (input.type === 'password') {
                    input.type = 'text';
                    e.target.classList.remove('fa-eye');
                    e.target.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    e.target.classList.remove('fa-eye-slash');
                    e.target.classList.add('fa-eye');
                }
            }
        });

        // Contact form validation (only if contact form exists on page)
        const contactForm = document.querySelector('.contact-form');
        if (contactForm) {
            contactForm.addEventListener('submit', function(e) {
                const name = document.getElementById('contactName').value.trim();
                const email = document.getElementById('contactEmail').value.trim();
                const subject = document.getElementById('contactSubject').value;
                const message = document.getElementById('contactMessage').value.trim();
                
                if (!name || !email || !subject || !message) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return;
                }
                
                if (!isValidEmail(email)) {
                    e.preventDefault();
                    alert('Please enter a valid email address.');
                    return;
                }
            });
        }
        
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
    </script>
</body>
</html>