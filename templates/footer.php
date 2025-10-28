    <footer id="colophon" class="site-footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="footer-section">
                        <h3><?php bloginfo('name'); ?></h3>
                        <p><?php bloginfo('description'); ?></p>
                        <div class="social-links">
                            <a href="#" class="social-link" title="Facebook">
                                <i class="fab fa-facebook"></i>
                            </a>
                            <a href="#" class="social-link" title="Twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="social-link" title="Telegram">
                                <i class="fab fa-telegram"></i>
                            </a>
                            <a href="#" class="social-link" title="Discord">
                                <i class="fab fa-discord"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-2">
                    <div class="footer-section">
                        <h4>Quick Links</h4>
                        <ul style="list-style: none; padding: 0;">
                            <li><a href="<?php echo home_url('/'); ?>">Home</a></li>
                            <?php if (is_user_logged_in()) : ?>
                                <li><a href="<?php echo home_url('/dashboard'); ?>">Dashboard</a></li>
                                <li><a href="<?php echo home_url('/invest'); ?>">Invest</a></li>
                                <li><a href="<?php echo home_url('/withdraw'); ?>">Withdraw</a></li>
                            <?php else : ?>
                                <li><a href="<?php echo home_url('/login'); ?>">Login</a></li>
                                <li><a href="<?php echo wp_registration_url(); ?>">Register</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="footer-section">
                        <h4>Support</h4>
                        <ul style="list-style: none; padding: 0;">
                            <li><a href="#contact">Contact Us</a></li>
                            <li><a href="#faq">FAQ</a></li>
                            <li><a href="#terms">Terms of Service</a></li>
                            <li><a href="#privacy">Privacy Policy</a></li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="footer-section">
                        <h4>Contact Info</h4>
                        <div class="contact-info">
                            <p>
                                <i class="fas fa-envelope"></i> 
                                <a href="mailto:support@hyipmanager.com">support@hyipmanager.com</a>
                            </p>
                            <p>
                                <i class="fas fa-phone"></i> 
                                <a href="tel:+15551234567">+1 (555) 123-4567</a>
                            </p>
                            <p>
                                <i class="fas fa-map-marker-alt"></i> 
                                123 Business St, Suite 100
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom" style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid rgba(255, 255, 255, 0.2); text-align: center;">
                <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. All rights reserved.</p>
                <p>HYIP Manager Theme v1.0.2</p>
            </div>
        </div>
    </footer>
</div><!-- #page -->

<!-- Alert Container for JavaScript Messages -->
<div id="alert-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>

<script>
// Helper function to show alerts
function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alert-container');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = `
        ${message}
        <button type="button" style="float: right; background: none; border: none; font-size: 1.2rem; cursor: pointer;" onclick="this.parentElement.remove()">
            &times;
        </button>
    `;
    alertContainer.appendChild(alert);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alert.parentElement) {
            alert.remove();
        }
    }, 5000);
}

// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    const primaryMenu = document.querySelector('#primary-menu');
    
    if (mobileToggle && primaryMenu) {
        mobileToggle.addEventListener('click', function() {
            primaryMenu.classList.toggle('mobile-active');
        });
    }
});
</script>

<style>
/* Footer Styles */
.site-footer {
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 3rem 0 1rem;
    margin-top: 4rem;
}

.footer-section h3, .footer-section h4 {
    margin-bottom: 1rem;
}

.footer-section ul li {
    margin-bottom: 0.5rem;
}

.footer-section a {
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: color 0.3s;
}

.footer-section a:hover {
    color: white;
}

.social-links {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.social-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    font-size: 1.2rem;
    transition: background-color 0.3s;
}

.social-link:hover {
    background: rgba(255, 255, 255, 0.2);
}

.contact-info p {
    margin-bottom: 0.5rem;
}

.contact-info i {
    margin-right: 0.5rem;
    width: 16px;
}

/* Mobile Menu */
.mobile-menu-toggle {
    display: none;
    flex-direction: column;
    cursor: pointer;
    gap: 3px;
}

.mobile-menu-toggle span {
    width: 25px;
    height: 3px;
    background: white;
    transition: all 0.3s;
}

@media (max-width: 768px) {
    .site-header .container {
        flex-wrap: wrap;
    }
    
    .mobile-menu-toggle {
        display: flex;
    }
    
    .main-navigation ul {
        display: none;
        width: 100%;
        flex-direction: column;
        gap: 1rem;
        margin-top: 1rem;
        padding: 1rem;
        background: rgba(0, 0, 0, 0.8);
        border-radius: 8px;
    }
    
    .main-navigation ul.mobile-active {
        display: flex;
    }
    
    .footer-section {
        margin-bottom: 2rem;
    }
}

/* Alert Styles Enhancement */
.alert {
    max-width: 400px;
    margin-bottom: 10px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
</style>

<?php wp_footer(); ?>
</body>
</html>