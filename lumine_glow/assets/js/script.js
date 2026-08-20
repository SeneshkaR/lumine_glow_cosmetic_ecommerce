document.addEventListener('DOMContentLoaded', () => {
    // Mobile menu toggle
    const menu = document.querySelector('.menu-toggle');
    const nav = document.querySelector('nav');

    if (menu && nav) {
        menu.addEventListener('click', () => nav.classList.toggle('open'));
        
        // Close menu on link click (mobile)
        nav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 850) {
                    nav.classList.remove('open');
                }
            });
        });
    }

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(link => {
        link.addEventListener('click', e => {
            const target = document.querySelector(link.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });

    // Auto-dismiss flash messages after 5 seconds
    const flashMessages = document.querySelectorAll('.flash-message');
    flashMessages.forEach(msg => {
        setTimeout(() => {
            msg.style.transition = 'opacity 0.5s';
            msg.style.opacity = '0';
            setTimeout(() => msg.remove(), 500);
        }, 5000);
    });

    // Quantity input validation
    document.querySelectorAll('.quantity').forEach(input => {
        input.addEventListener('change', function() {
            const min = parseInt(this.min) || 1;
            const max = parseInt(this.max) || 999;
            let val = parseInt(this.value) || min;
            
            if (val < min) this.value = min;
            if (val > max) this.value = max;
        });
    });

    // Add loading state to forms on submit
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"], .btn[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                submitBtn.textContent = submitBtn.textContent || 'Processing...';
            }
        });
    });
});