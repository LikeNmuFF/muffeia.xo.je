<?php 
// Set page title for header
$page_title = "Contact Us - Muffeia";
include '../includes/header.php'; 
?>

        <section class="page-hero">
            <h1>Contact Us</h1>
            <p>We're here to help. Reach out with questions, feedback, or concerns.</p>
        </section>

        <section class="content-section">
            <div class="contact-content">
                <div class="contact-info">
                    <h2>Get In Touch</h2>
                    <p>Have questions about Muffeia? Need support with your account? Want to provide feedback? We'd love to hear from you.</p>
                    
                    <div class="contact-methods">
                        <div class="contact-method">
                            <div class="method-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="method-details">
                                <h3>Email Us</h3>
                                <p>muff.muffeia@gmail.com</p>
                                <p>We typically respond within 2 business days</p>
                            </div>
                        </div>
                        
                        <div class="contact-method">
                            <div class="method-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="method-details">
                                <h3>Privacy & Security</h3>
                                <p>muff.muffeia@gmail.com</p>
                                <p>For questions about data protection and security</p>
                            </div>
                        </div>
                        
                        <div class="contact-method">
                            <div class="method-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="method-details">
                                <h3>Community Guidelines</h3>
                                <p>muff.muffeia@gmail.com</p>
                                <p>For reports and questions about community standards</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="response-time">
                        <h3>Response Times</h3>
                        <ul>
                            <li><strong>General Inquiries:</strong> Within 2 business days</li>
                            <li><strong>Account Issues:</strong> Within 1 business day</li>
                            <li><strong>Urgent Safety Concerns:</strong> Within 24 hours</li>
                            <li><strong>Community Guidelines Reports:</strong> Within 2 business days</li>
                        </ul>
                    </div>
                </div>
                
                <div class="contact-form-container">
                    <div class="form-header">
                        <h2>Send Us a Message</h2>
                        <p>Fill out the form below and we'll get back to you as soon as possible.</p>
                    </div>
                    
                    <?php if ($contact_success): ?>
                        <div class="success-message">
                            <i class="fas fa-check-circle"></i>
                            <?php echo $contact_success; ?>
                        </div>
                    <?php elseif ($contact_error): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $contact_error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form class="contact-form" method="POST" action="">
                        <input type="hidden" name="action" value="contact">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="contactName">Your Name *</label>
                                <input type="text" id="contactName" name="name" class="form-input" placeholder="Enter your name" value="<?php echo $form_name; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="contactEmail">Email Address *</label>
                                <input type="email" id="contactEmail" name="email" class="form-input" placeholder="Enter your email" value="<?php echo $form_email; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="contactSubject">Subject *</label>
                            <select id="contactSubject" name="subject" class="form-input" required>
                                <option value="">Select a subject</option>
                                <option value="General Inquiry" <?php echo ($form_subject == 'General Inquiry') ? 'selected' : ''; ?>>General Inquiry</option>
                                <option value="Account Support" <?php echo ($form_subject == 'Account Support') ? 'selected' : ''; ?>>Account Support</option>
                                <option value="Technical Issue" <?php echo ($form_subject == 'Technical Issue') ? 'selected' : ''; ?>>Technical Issue</option>
                                <option value="Community Guidelines" <?php echo ($form_subject == 'Community Guidelines') ? 'selected' : ''; ?>>Community Guidelines</option>
                                <option value="Privacy Concern" <?php echo ($form_subject == 'Privacy Concern') ? 'selected' : ''; ?>>Privacy Concern</option>
                                <option value="Feedback" <?php echo ($form_subject == 'Feedback') ? 'selected' : ''; ?>>Feedback</option>
                                <option value="Partnership" <?php echo ($form_subject == 'Partnership') ? 'selected' : ''; ?>>Partnership</option>
                                <option value="Other" <?php echo ($form_subject == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="contactMessage">Message *</label>
                            <textarea id="contactMessage" name="message" class="form-input" placeholder="How can we help you?" rows="6" required><?php echo $form_message; ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <section class="faq-section">
            <h2 class="section-title">Frequently Asked Questions</h2>
            
            <div class="faq-grid">
                <div class="faq-item">
                    <h3>How do I reset my password?</h3>
                    <p>Click on "Forgot your password?" on the login page and follow the instructions sent to your email.</p>
                </div>
                
                <div class="faq-item">
                    <h3>Is Muffeia really anonymous?</h3>
                    <p>Yes, when you post anonymously, your identity is not visible to other users. We have strict privacy measures in place to protect your anonymity.</p>
                </div>
                
                <div class="faq-item">
                    <h3>How do I report inappropriate content?</h3>
                    <p> Email us @ muff.muffeia@gmail.com with details about the content for faster response.</p>
                </div>
                
                <div class="faq-item">
                    <h3>Can I delete my account?</h3>
                    <p>Yes, you can permanently delete your account. For now you need to Email us @ muff.muffeia@gmail.com for faster response.</p>
                </div>
                
                <div class="faq-item">
                    <h3>Is there a mobile app?</h3>
                    <p>Not yet, but our website is fully responsive and works well on mobile devices. We're working on a dedicated mobile app.</p>
                </div>
                
                <div class="faq-item">
                    <h3>How is Muffeia funded?</h3>
                    <p>Not yet. Muffeia was built through school projects and is hosted for free. We're committed to keeping the platform free and accessible to all.</p>
                </div>
            </div>
        </section>

<?php include '../includes/footer.php'; ?>