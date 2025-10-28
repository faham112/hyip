/**
 * Main JavaScript for HYIP Manager Theme
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Mobile menu toggle
    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    const primaryMenu = document.querySelector('#primary-menu');
    
    if (mobileToggle && primaryMenu) {
        mobileToggle.addEventListener('click', function() {
            primaryMenu.classList.toggle('mobile-active');
        });
    }
    
    // Form validation helpers
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    function validatePassword(password) {
        return password.length >= 8;
    }
    
    // Registration form validation
    const registerForm = document.getElementById('registerform');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            const password = document.getElementById('user_pass').value;
            const confirmPassword = document.getElementById('user_pass_confirm').value;
            const email = document.getElementById('user_email').value;
            const termsChecked = document.getElementById('terms_agree').checked;
            
            if (!validatePassword(password)) {
                e.preventDefault();
                showAlert('Password must be at least 8 characters long.', 'danger');
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                showAlert('Passwords do not match.', 'danger');
                return false;
            }
            
            if (!validateEmail(email)) {
                e.preventDefault();
                showAlert('Please enter a valid email address.', 'danger');
                return false;
            }
            
            if (!termsChecked) {
                e.preventDefault();
                showAlert('You must agree to the Terms of Service.', 'danger');
                return false;
            }
        });
        
        // Real-time password confirmation
        const passwordField = document.getElementById('user_pass');
        const confirmField = document.getElementById('user_pass_confirm');
        
        if (passwordField && confirmField) {
            confirmField.addEventListener('input', function() {
                if (passwordField.value && confirmField.value) {
                    if (passwordField.value !== confirmField.value) {
                        confirmField.setCustomValidity('Passwords do not match');
                    } else {
                        confirmField.setCustomValidity('');
                    }
                }
            });
        }
    }
    
    // Investment form handling
    const investmentForm = document.getElementById('investmentForm');
    if (investmentForm) {
        const amountInput = document.getElementById('amount');
        const planSelect = document.getElementById('plan_id');
        
        if (amountInput && planSelect) {
            amountInput.addEventListener('input', updateInvestmentSummary);
            planSelect.addEventListener('change', updateInvestmentSummary);
        }
    }
    
    function updateInvestmentSummary() {
        const amount = parseFloat(document.getElementById('amount').value) || 0;
        const planSelect = document.getElementById('plan_id');
        
        if (!planSelect || !planSelect.selectedOptions[0]) return;
        
        const selectedOption = planSelect.selectedOptions[0];
        const dailyPercent = parseFloat(selectedOption.dataset.dailyPercent) || 0;
        const duration = parseInt(selectedOption.dataset.duration) || 0;
        
        const dailyProfit = (amount * dailyPercent) / 100;
        const totalProfit = dailyProfit * duration;
        const totalReturn = amount + totalProfit;
        
        // Update summary display
        const summaryAmount = document.getElementById('summary_amount');
        const summaryDaily = document.getElementById('summary_daily');
        const summaryProfit = document.getElementById('summary_profit');
        const summaryTotal = document.getElementById('summary_total');
        
        if (summaryAmount) summaryAmount.textContent = '$' + amount.toFixed(2);
        if (summaryDaily) summaryDaily.textContent = '$' + dailyProfit.toFixed(2);
        if (summaryProfit) summaryProfit.textContent = '$' + totalProfit.toFixed(2);
        if (summaryTotal) summaryTotal.textContent = '$' + totalReturn.toFixed(2);
    }
    
    // Withdrawal form handling
    const withdrawalForm = document.getElementById('withdrawalForm');
    if (withdrawalForm) {
        const amountInput = document.getElementById('amount');
        const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
        const paymentOptions = document.querySelectorAll('.payment-option');
        
        // Payment method selection styling
        paymentMethods.forEach(method => {
            method.addEventListener('change', function() {
                paymentOptions.forEach(option => {
                    option.style.borderColor = '#e9ecef';
                    option.style.background = 'white';
                });
                if (this.checked) {
                    this.closest('.payment-option').style.borderColor = '#667eea';
                    this.closest('.payment-option').style.background = '#f8f9ff';
                }
            });
        });
        
        // Update withdrawal summary
        if (amountInput) {
            amountInput.addEventListener('input', updateWithdrawalSummary);
        }
    }
    
    function updateWithdrawalSummary() {
        const amount = parseFloat(document.getElementById('amount').value) || 0;
        const fee = 2.00; // Standard processing fee
        const net = Math.max(0, amount - fee);
        
        const summaryAmount = document.getElementById('summary_amount');
        const summaryNet = document.getElementById('summary_net');
        
        if (summaryAmount) summaryAmount.textContent = '$' + amount.toFixed(2);
        if (summaryNet) summaryNet.textContent = '$' + net.toFixed(2);
    }
    
    // Auto-refresh for dashboard (every 5 minutes)
    if (document.body.classList.contains('page-template-page-dashboard')) {
        setInterval(function() {
            // Only refresh if user is still active (not idle)
            if (document.hasFocus()) {
                location.reload();
            }
        }, 300000); // 5 minutes
    }
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Copy to clipboard functionality
    window.copyToClipboard = function(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                showAlert('Copied to clipboard!', 'success');
            });
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showAlert('Copied to clipboard!', 'success');
        }
    };
    
    // Number formatting
    window.formatCurrency = function(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    };
    
    // Time ago formatting
    window.timeAgo = function(date) {
        const seconds = Math.floor((new Date() - new Date(date)) / 1000);
        
        let interval = seconds / 31536000;
        if (interval > 1) return Math.floor(interval) + ' years ago';
        
        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + ' months ago';
        
        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + ' days ago';
        
        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + ' hours ago';
        
        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + ' minutes ago';
        
        return 'just now';
    };
    
    // Form loading states
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitButton) {
                const originalText = submitButton.textContent || submitButton.value;
                submitButton.disabled = true;
                
                if (submitButton.tagName === 'BUTTON') {
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                } else {
                    submitButton.value = 'Processing...';
                }
                
                // Re-enable after 30 seconds to prevent permanent lock
                setTimeout(() => {
                    submitButton.disabled = false;
                    if (submitButton.tagName === 'BUTTON') {
                        submitButton.textContent = originalText;
                    } else {
                        submitButton.value = originalText;
                    }
                }, 30000);
            }
        });
    });
    
    // Tooltips for info icons
    document.querySelectorAll('[data-tooltip]').forEach(element => {
        element.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            tooltip.textContent = this.dataset.tooltip;
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + 'px';
            tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
        });
        
        element.addEventListener('mouseleave', function() {
            const tooltip = document.querySelector('.custom-tooltip');
            if (tooltip) {
                tooltip.remove();
            }
        });
    });
    
    // Print functionality
    window.printPage = function() {
        window.print();
    };
    
    // Export functionality (placeholder)
    window.exportData = function(format) {
        showAlert('Export functionality will be implemented in a future update.', 'info');
    };
    
    // Initialize any countdown timers
    document.querySelectorAll('[data-countdown]').forEach(element => {
        const targetTime = new Date(element.dataset.countdown).getTime();
        
        const updateCountdown = () => {
            const now = new Date().getTime();
            const distance = targetTime - now;
            
            if (distance < 0) {
                element.textContent = 'Expired';
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            element.textContent = `${days}d ${hours}h ${minutes}m ${seconds}s`;
        };
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
    });
});

// AJAX Helper Functions
window.hyipAjax = {
    request: function(action, data, callback) {
        if (typeof hyip_ajax === 'undefined') {
            console.error('HYIP AJAX not properly initialized');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', hyip_ajax.nonce);
        
        for (const key in data) {
            formData.append(key, data[key]);
        }
        
        fetch(hyip_ajax.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(callback)
        .catch(error => {
            console.error('AJAX Error:', error);
            showAlert('An error occurred. Please try again.', 'danger');
        });
    },
    
    processInvestment: function(planId, amount, callback) {
        this.request('hyip_create_investment', {
            plan_id: planId,
            amount: amount
        }, callback);
    },
    
    processWithdrawal: function(amount, paymentMethod, paymentDetails, callback) {
        this.request('hyip_create_withdrawal', {
            amount: amount,
            payment_method: paymentMethod,
            payment_details: paymentDetails
        }, callback);
    }
};

// CSS for dynamic elements
const style = document.createElement('style');
style.textContent = `
    .custom-tooltip {
        position: absolute;
        background: #333;
        color: white;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        z-index: 9999;
        pointer-events: none;
    }
    
    .custom-tooltip:after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        margin-left: -5px;
        border-width: 5px;
        border-style: solid;
        border-color: #333 transparent transparent transparent;
    }
    
    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid #3498db;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);