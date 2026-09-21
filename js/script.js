// ===== Responsive Favicon for Dark / Light Mode =====
(function initResponsiveFavicon() {
    function updateFavicon() {
        const isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const iconPath = isDark ? 'assets/images/logo_icon_white.webp' : 'assets/images/logo_icon.webp';
        
        const favicons = document.querySelectorAll('link[rel="icon"]');
        favicons.forEach(link => {
            if (!link.hasAttribute('media') || link.id === 'app-favicon') {
                link.href = iconPath;
            }
        });
    }

    if (window.matchMedia) {
        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        if (mq.addEventListener) {
            mq.addEventListener('change', updateFavicon);
        } else if (mq.addListener) {
            mq.addListener(updateFavicon);
        }
    }
    updateFavicon();
})();

// ===== Sticky header scroll state =====
const mainHeader = document.getElementById('main-header');
if (mainHeader) {
    window.addEventListener('scroll', () => {
        if (window.scrollY > 60) {
            mainHeader.classList.add('scrolled');
        } else {
            mainHeader.classList.remove('scrolled');
        }
    }, { passive: true });
}

// ===== Scroll-reveal for sections =====
(function () {
    const revealTargets = document.querySelectorAll(
        '.stat-col, .process-text, .process-media, .service-card, .property-card, .testi-inner, .contact-info-panel, .contact-form-panel'
    );
    if (!revealTargets.length) return;
    revealTargets.forEach(el => el.classList.add('reveal'));

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, i) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.classList.add('visible');
                }, i * 80);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    revealTargets.forEach(el => observer.observe(el));
})();


document.addEventListener('DOMContentLoaded', () => {

    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // ===== Phone Number Formatter: (xxx) xxx-xxxx strictly max 10 digits =====
    function initPhoneInputs() {
        const phoneInputs = document.querySelectorAll('input[type="tel"], input[name="phone"], .us-phone-input');
        phoneInputs.forEach(input => {
            input.setAttribute('placeholder', '(xxx) xxx-xxxx');
            input.setAttribute('maxlength', '14');

            let isBackspace = false;
            input.addEventListener('keydown', (e) => {
                isBackspace = (e.key === 'Backspace' || e.key === 'Delete');
            });

            input.addEventListener('input', () => {
                let val = input.value;
                let digits = val.replace(/\D/g, '');
                if (digits.length === 11 && digits.startsWith('1')) {
                    digits = digits.substring(1);
                }
                digits = digits.substring(0, 10); // Strictly max 10 digits

                if (isBackspace && (val.endsWith(')') || val.endsWith('-') || val.endsWith(' '))) {
                    return;
                }

                const len = digits.length;
                if (len === 0) {
                    input.value = '';
                } else if (len < 3) {
                    input.value = `(${digits}`;
                } else if (len === 3) {
                    input.value = isBackspace ? `(${digits}` : `(${digits}) `;
                } else if (len <= 6) {
                    input.value = `(${digits.slice(0, 3)}) ${digits.slice(3)}`;
                } else {
                    input.value = `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6, 10)}`;
                }
            });

            input.addEventListener('blur', () => {
                let digits = input.value.replace(/\D/g, '');
                if (digits.length === 11 && digits.startsWith('1')) digits = digits.substring(1);
                digits = digits.substring(0, 10);
                if (digits.length === 10) {
                    input.value = `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6, 10)}`;
                }
            });
        });
    }
    initPhoneInputs();

    // Form submission handling for all lead forms
    const submitForms = document.querySelectorAll('form[action="php/submit.php"], #leadForm, #buyerLeadForm');
    submitForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn ? submitBtn.innerText : 'Sending...';
            if (submitBtn) {
                submitBtn.innerText = 'Sending...';
                submitBtn.disabled = true;
            }

            const formData = new FormData(this);

            fetch('php/submit.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    form.reset();
                    if (typeof turnstile !== 'undefined') {
                        try { turnstile.reset(); } catch(err) {}
                    }
                    window.location.href = 'thank-you.html';
                } else {
                    alert(data.message);
                    if (typeof turnstile !== 'undefined') {
                        try { turnstile.reset(); } catch(err) {}
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.innerText = originalBtnText;
                    submitBtn.disabled = false;
                }
            });
        });
    });

    // Optional: Add simple header background on scroll
    const header = document.querySelector('header');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            header.style.background = 'rgba(15, 23, 42, 0.95)';
            header.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1)';
        } else {
            header.style.background = 'linear-gradient(to bottom, rgba(15,23,42,0.8), transparent)';
            header.style.boxShadow = 'none';
        }
    });



    // Custom State Search Dropdown
    const stateSearchInput = document.getElementById('state_search');
    const stateHiddenInput = document.getElementById('state');
    const stateDropdown = document.getElementById('state_dropdown');
    
    const usStates = [
        "AL - Alabama", "AK - Alaska", "AZ - Arizona", "AR - Arkansas", "CA - California", 
        "CO - Colorado", "CT - Connecticut", "DE - Delaware", "FL - Florida", "GA - Georgia", 
        "HI - Hawaii", "ID - Idaho", "IL - Illinois", "IN - Indiana", "IA - Iowa", 
        "KS - Kansas", "KY - Kentucky", "LA - Louisiana", "ME - Maine", "MD - Maryland", 
        "MA - Massachusetts", "MI - Michigan", "MN - Minnesota", "MS - Mississippi", "MO - Missouri", 
        "MT - Montana", "NE - Nebraska", "NV - Nevada", "NH - New Hampshire", "NJ - New Jersey", 
        "NM - New Mexico", "NY - New York", "NC - North Carolina", "ND - North Dakota", "OH - Ohio", 
        "OK - Oklahoma", "OR - Oregon", "PA - Pennsylvania", "RI - Rhode Island", "SC - South Carolina", 
        "SD - South Dakota", "TN - Tennessee", "TX - Texas", "UT - Utah", "VT - Vermont", 
        "VA - Virginia", "WA - Washington", "WV - West Virginia", "WI - Wisconsin", "WY - Wyoming"
    ];

    if (stateSearchInput && stateDropdown) {
        const populateStates = (filter = '') => {
            stateDropdown.innerHTML = '';
            const matches = usStates.filter(s => s.toLowerCase().includes(filter.toLowerCase()));
            if (matches.length > 0) {
                matches.forEach(stateStr => {
                    const li = document.createElement('li');
                    li.textContent = stateStr;
                    li.addEventListener('click', () => {
                        stateSearchInput.value = stateStr;
                        stateHiddenInput.value = stateStr.split(' - ')[0]; // Store abbreviation
                        stateDropdown.classList.add('hidden');
                    });
                    stateDropdown.appendChild(li);
                });
            } else {
                const li = document.createElement('li');
                li.textContent = 'No states found';
                li.style.color = '#cbd5e1';
                li.style.pointerEvents = 'none';
                stateDropdown.appendChild(li);
            }
        };

        stateSearchInput.addEventListener('focus', () => {
            populateStates(stateSearchInput.value);
            stateDropdown.classList.remove('hidden');
        });

        stateSearchInput.addEventListener('input', (e) => {
            stateHiddenInput.value = ''; // Reset hidden input if they type manually
            populateStates(e.target.value);
            stateDropdown.classList.remove('hidden');
        });

        // Hide dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (e.target !== stateSearchInput && e.target !== stateDropdown) {
                stateDropdown.classList.add('hidden');
            }
        });
    }

    // Seller Lead Form Submission
    const sellerLeadForm = document.getElementById('sellerLeadForm');
    if (sellerLeadForm) {
        sellerLeadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerText;
            submitBtn.innerText = 'Submitting...';
            submitBtn.disabled = true;

            const formData = new FormData(this);

            fetch('php/sell_submit.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    sellerLeadForm.reset();
                    if(typeof turnstile !== 'undefined') turnstile.reset();
                    window.location.href = 'thank-you.html';
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            })
            .finally(() => {
                submitBtn.innerText = originalBtnText;
                submitBtn.disabled = false;
            });
        });
    }

    // Testimonials Carousel
    const carousels = document.querySelectorAll('.testi-carousel');
    carousels.forEach(carousel => {
        const wrapper = carousel.closest('.testi-carousel-wrapper');
        const prevBtn = wrapper.querySelector('.prev-btn');
        const nextBtn = wrapper.querySelector('.next-btn');

        if (prevBtn && nextBtn) {
            prevBtn.addEventListener('click', () => {
                const slide = carousel.querySelector('.testi-slide');
                const gap = parseFloat(getComputedStyle(carousel).gap) || 0;
                const scrollAmount = slide.offsetWidth + gap;
                carousel.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
            });

            nextBtn.addEventListener('click', () => {
                const slide = carousel.querySelector('.testi-slide');
                const gap = parseFloat(getComputedStyle(carousel).gap) || 0;
                const scrollAmount = slide.offsetWidth + gap;
                carousel.scrollBy({ left: scrollAmount, behavior: 'smooth' });
            });
        }
    });
});

// Dynamically load the global footer
document.addEventListener("DOMContentLoaded", function() {
    const footerContainer = document.getElementById('global-footer-container');
    if (footerContainer) {
        fetch('php/footer.php')
            .then(response => {
                if (!response.ok) throw new Error('Footer not found');
                return response.text();
            })
            .then(html => {
                footerContainer.innerHTML = html;
            })
            .catch(error => console.error('Error loading footer:', error));
    }

    // Mobile navigation toggle
    const mobileNavToggle = document.getElementById('mobileNavToggle');
    const mainNav = document.querySelector('.main-nav');
    if (mobileNavToggle && mainNav) {
        mobileNavToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            mobileNavToggle.classList.toggle('active');
            mainNav.classList.toggle('active');
        });

        document.addEventListener('click', (e) => {
            if (!mobileNavToggle.contains(e.target) && !mainNav.contains(e.target)) {
                mobileNavToggle.classList.remove('active');
                mainNav.classList.remove('active');
            }
        });

        mainNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                mobileNavToggle.classList.remove('active');
                mainNav.classList.remove('active');
            });
        });
    }
});
