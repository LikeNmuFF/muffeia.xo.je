<?php
// footer.php
?>
        <footer class="site-footer">
            <div class="footer-links">
                <a href="/about">About Us</a>
                <a href="/privacy">Privacy Policy</a>
                <a href="/guidelines">Community Guidelines</a>
                <a href="/resources">Mental Health Resources</a>
                <a href="/contact">Contact</a>
            </div>
            <p>&copy; 2025 Muffeia. Built with security and compassion.</p>
        </footer>
    </div>

    <script>
        // Modal functionality
        const loginModal = document.getElementById('loginModal');
        const registerModal = document.getElementById('registerModal');
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

        if (document.getElementById('loginBtn')) {
            document.getElementById('loginBtn').addEventListener('click', function() {
                window.location.href = '/auth/login';
            });
        }
        if (document.getElementById('contact')) {
            document.getElementById('contact').addEventListener('click', function() {
                window.location.href = 'contact';
            });
        }

        closeModalBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                closeModal(loginModal);
                closeModal(registerModal);
            });
        });

        if (switchToRegister) {
            switchToRegister.addEventListener('click', (e) => {
                e.preventDefault();
                closeModal(loginModal);
                openModal(registerModal);
            });
        }

        if (switchToLogin) {
            switchToLogin.addEventListener('click', (e) => {
                e.preventDefault();
                closeModal(registerModal);
                openModal(loginModal);
            });
        }

        window.addEventListener('click', (e) => {
            if (e.target === loginModal) closeModal(loginModal);
            if (e.target === registerModal) closeModal(registerModal);
        });

        // Password toggle
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('password-toggle')) {
                const targetId = e.target.dataset.target;
                const input = document.getElementById(targetId);
                if (input) {
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
            }
        });

        // Mobile menu toggle
        const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
        const nav = document.querySelector('nav');
        
        if (mobileMenuBtn && nav) {
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
        }

        // Contact form validation
        const contactForm = document.querySelector('.contact-form');
        if (contactForm) {
            contactForm.addEventListener('submit', function(e) {
                const name = document.getElementById('contactName')?.value.trim();
                const email = document.getElementById('contactEmail')?.value.trim();
                const subject = document.getElementById('contactSubject')?.value;
                const message = document.getElementById('contactMessage')?.value.trim();
                
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
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }
    </script>
</body>
</html>
