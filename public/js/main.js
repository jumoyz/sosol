document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const mobileMenuToggle = document.querySelector('[data-toggle="mobile-menu"]');
    const mobileMenu = document.querySelector('[data-mobile-menu]');
    
    if (mobileMenuToggle && mobileMenu) {
        mobileMenuToggle.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
            this.setAttribute('aria-expanded', mobileMenu.classList.contains('hidden') ? 'false' : 'true');
        });
    }
    
    // Mobile sidebar toggle
    const mobileSidebarToggle = document.querySelector('[data-toggle="mobile-sidebar"]');
    const mobileSidebar = document.querySelector('[data-mobile-sidebar]');
    const overlay = document.querySelector('[data-overlay]');
    const closeButtons = document.querySelectorAll('[data-close]');
    
    if (mobileSidebarToggle && mobileSidebar && overlay) {
        mobileSidebarToggle.addEventListener('click', function() {
            mobileSidebar.classList.add('show');
            overlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
        
        overlay.addEventListener('click', function() {
            mobileSidebar.classList.remove('show');
            this.classList.add('hidden');
            document.body.style.overflow = '';
        });
        
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const target = this.getAttribute('data-close');
                if (target) {
                    document.querySelector(`[data-${target}]`).classList.remove('show');
                    overlay.classList.add('hidden');
                    document.body.style.overflow = '';
                }
            });
        });
    }
    
    // Dismiss alerts
    const alertDismissButtons = document.querySelectorAll('[data-dismiss="alert"]');
    alertDismissButtons.forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.alert').classList.add('hidden');
        });
    });
    
    // Flash messages auto-dismiss
    const flashMessages = document.querySelectorAll('.alert');
    flashMessages.forEach(message => {
        setTimeout(() => {
            message.classList.add('opacity-0', 'transition-opacity', 'duration-500');
            setTimeout(() => message.remove(), 500);
        }, 5000);
    });
    
    // Tooltips
    const tooltipTriggers = document.querySelectorAll('[data-tooltip]');
    tooltipTriggers.forEach(trigger => {
        trigger.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'absolute z-50 py-1 px-2 bg-gray-800 text-white text-xs rounded whitespace-nowrap';
            tooltip.textContent = this.getAttribute('data-tooltip');
            
            const rect = this.getBoundingClientRect();
            tooltip.style.top = `${rect.top - 30}px`;
            tooltip.style.left = `${rect.left + rect.width / 2}px`;
            tooltip.style.transform = 'translateX(-50%)';
            
            document.body.appendChild(tooltip);
            this._tooltip = tooltip;
        });
        
        trigger.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
            }
        });
    });
    
    // Smooth scrolling for anchor links
    /*
    document.querySelectorAll('a[href^="#"]').forEach(anchor => { 
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
    */

    // Smooth scrolling for anchor links (excluding Bootstrap dropdown toggles)
// Smooth scrolling for anchor links (skip dropdown toggles)
document.querySelectorAll('a[href^="#"]:not([data-bs-toggle="dropdown"])').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const href = this.getAttribute('href');

        if (!href || href === '#') return; // skip empty / #

        const target = document.querySelector(href);
        if (target) {
            e.preventDefault(); // prevent default only for scrolling
            target.scrollIntoView({ behavior: 'smooth' });
        }
    });
});




});