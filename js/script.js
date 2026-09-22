// ===== Cloudflare Turnstile Safe Explicit Loader =====
window.onloadTurnstileCallback = function() {
    if (typeof turnstile === 'undefined') return;
    const turnstileElements = document.querySelectorAll('.cf-turnstile');
    turnstileElements.forEach(el => {
        if (el.dataset.rendered) return;
        const sitekey = el.getAttribute('data-sitekey');
        if (sitekey && !sitekey.startsWith('YOUR_') && sitekey.trim() !== '') {
            try {
                turnstile.render(el, {
                    sitekey: sitekey,
                    theme: el.getAttribute('data-theme') || 'light',
                    size: el.getAttribute('data-size') || 'invisible'
                });
                el.dataset.rendered = 'true';
            } catch (err) {
                console.warn('Turnstile initialization notice:', err);
            }
        }
    });
};

// Auto-run if Turnstile is already loaded when script executes
if (typeof turnstile !== 'undefined') {
    window.onloadTurnstileCallback();
}

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

    // ===== Phone Number Formatter: (123) 456-7890 strictly max 10 digits & block alphabets =====
    function initPhoneInputs() {
        const phoneInputs = document.querySelectorAll('input[type="tel"], input[name="phone"], .us-phone-input');
        
        function formatPhone(digits, isDeleting = false) {
            if (!digits) return '';
            const len = digits.length;
            if (len === 0) return '';
            if (len < 3) {
                return `(${digits}`;
            }
            if (len === 3) {
                return isDeleting ? `(${digits}` : `(${digits}) `;
            }
            if (len < 6) {
                return `(${digits.slice(0, 3)}) ${digits.slice(3)}`;
            }
            if (len === 6) {
                return isDeleting ? `(${digits.slice(0, 3)}) ${digits.slice(3)}` : `(${digits.slice(0, 3)}) ${digits.slice(3)}-`;
            }
            return `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6, 10)}`;
        }

        function getCursorForDigitCount(str, count) {
            if (count <= 0) return 0;
            let seen = 0;
            for (let i = 0; i < str.length; i++) {
                if (/\d/.test(str[i])) {
                    seen++;
                    if (seen === count) return i + 1;
                }
            }
            return str.length;
        }

        phoneInputs.forEach(input => {
            if (input.dataset.phoneBound) return;
            input.dataset.phoneBound = 'true';
            input.setAttribute('placeholder', '(123) 456-7890');
            input.setAttribute('maxlength', '14');
            input.setAttribute('inputmode', 'numeric');

            input.addEventListener('keydown', (e) => {
                const allowedControlKeys = [
                    'Backspace', 'Delete', 'Tab', 'Escape', 'Enter',
                    'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown',
                    'Home', 'End'
                ];
                if (allowedControlKeys.includes(e.key)) {
                    if (e.key === 'Backspace' && input.selectionStart === input.selectionEnd) {
                        const pos = input.selectionStart;
                        const val = input.value;
                        if (pos > 0 && /[\s\-\)]/.test(val[pos - 1])) {
                            e.preventDefault();
                            let targetDigitIndex = pos - 1;
                            while (targetDigitIndex >= 0 && /\D/.test(val[targetDigitIndex])) {
                                targetDigitIndex--;
                            }
                            if (targetDigitIndex >= 0) {
                                const digitsBefore = val.slice(0, targetDigitIndex).replace(/\D/g, '').length;
                                const newVal = val.slice(0, targetDigitIndex) + val.slice(pos);
                                let digits = newVal.replace(/\D/g, '');
                                if (digits.length === 11 && digits.startsWith('1')) digits = digits.slice(1);
                                digits = digits.slice(0, 10);
                                input.value = formatPhone(digits, true);
                                const newPos = getCursorForDigitCount(input.value, digitsBefore);
                                input.setSelectionRange(newPos, newPos);
                            }
                        }
                    }
                    return;
                }

                // Allow Ctrl/Cmd combinations (Copy, Cut, Paste, Select All, Undo)
                if (e.ctrlKey || e.metaKey) {
                    return;
                }

                // STRICTLY BLOCK ALL ALPHABETS AND NON-DIGIT CHARACTERS
                if (!/^[0-9]$/.test(e.key)) {
                    e.preventDefault();
                    return;
                }

                // Strictly limit input to 10 digits
                let digits = input.value.replace(/\D/g, '');
                if (digits.length === 11 && digits.startsWith('1')) digits = digits.slice(1);
                if (digits.length >= 10 && input.selectionStart === input.selectionEnd) {
                    e.preventDefault();
                    return;
                }
            });

            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedText = (e.clipboardData || window.clipboardData).getData('text') || '';
                let digits = pastedText.replace(/\D/g, '');
                if (digits.length === 11 && digits.startsWith('1')) digits = digits.slice(1);
                digits = digits.slice(0, 10);
                input.value = formatPhone(digits, false);
            });

            input.addEventListener('input', (e) => {
                const isDeleting = (e.inputType === 'deleteContentBackward' || e.inputType === 'deleteContentForward');
                let digits = input.value.replace(/\D/g, '');
                if (digits.length === 11 && digits.startsWith('1')) {
                    digits = digits.substring(1);
                }
                digits = digits.substring(0, 10); // Strictly max 10 digits

                input.value = formatPhone(digits, isDeleting);
            });

            input.addEventListener('blur', () => {
                let digits = input.value.replace(/\D/g, '');
                if (digits.length === 11 && digits.startsWith('1')) digits = digits.slice(1);
                digits = digits.slice(0, 10);
                if (digits.length === 10) {
                    input.value = `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6, 10)}`;
                } else if (digits.length === 0) {
                    input.value = '';
                } else {
                    input.value = formatPhone(digits, true);
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
                    window.location.href = 'thank-you';
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
                    if (typeof turnstile !== 'undefined') {
                        try { turnstile.reset(); } catch(err) {}
                    }
                    window.location.href = 'thank-you';
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
                submitBtn.innerText = originalBtnText;
                submitBtn.disabled = false;
            });
        });
    }

    // Testimonials & Portfolio Carousel
    function initCarousels() {
        const carousels = document.querySelectorAll('.testi-carousel, .portfolio-carousel');
        carousels.forEach(carousel => {
            if (carousel.dataset.carouselBound) return;
            carousel.dataset.carouselBound = 'true';

            const wrapper = carousel.closest('.testi-carousel-wrapper') || carousel.parentElement;
            if (!wrapper) return;
            const prevBtn = wrapper.querySelector('.prev-btn');
            const nextBtn = wrapper.querySelector('.next-btn');

            if (prevBtn) {
                prevBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const slide = carousel.querySelector('.testi-slide, .property-card');
                    const gap = parseFloat(getComputedStyle(carousel).gap) || 24;
                    const scrollAmount = slide ? (slide.offsetWidth + gap) : (carousel.clientWidth * 0.85);
                    carousel.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const slide = carousel.querySelector('.testi-slide, .property-card');
                    const gap = parseFloat(getComputedStyle(carousel).gap) || 24;
                    const scrollAmount = slide ? (slide.offsetWidth + gap) : (carousel.clientWidth * 0.85);
                    carousel.scrollBy({ left: scrollAmount, behavior: 'smooth' });
                });
            }
        });
    }
    initCarousels();
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
