/**
 * ONBOLT DIGITAL EXPERIENCE PLATFORM - MAIN ENGINE
 * Pure Vanilla JavaScript + Three.js 3D Engine + GSAP Motion Suite
 * 3-Second Cinematic Preloader & Authentic Brand Identity System
 */

document.addEventListener('DOMContentLoaded', () => {
    initCustomCursor();
    initPreloader();
    initHeaderScroll();
    initMobileNav();
    initThreeJsHero();
    init3DTiltCards();
    initAnimations();
    initPageSpecificAnimations();
    initAjaxForms();
    initCartInteractions();
    initSizeChartModal();
    initSmoothScrollButtons();
});

// ==================== 1. HIGH-END CUSTOM FLUID MAGNETIC CURSOR ====================
function initCustomCursor() {
    // Only enable on desktop pointer devices
    if (window.matchMedia('(pointer: coarse)').matches) return;

    const dot = document.getElementById('cursor-dot');
    const ring = document.getElementById('cursor-ring');
    if (!dot || !ring) return;

    let mouseX = window.innerWidth / 2;
    let mouseY = window.innerHeight / 2;
    let ringX = mouseX;
    let ringY = mouseY;
    let isHovering = false;

    window.addEventListener('mousemove', (e) => {
        mouseX = e.clientX;
        mouseY = e.clientY;
        dot.style.transform = `translate(${mouseX}px, ${mouseY}px)`;
    }, { passive: true });

    function renderCursor() {
        // Smooth lerp trailing for ring
        ringX += (mouseX - ringX) * 0.18;
        ringY += (mouseY - ringY) * 0.18;
        ring.style.transform = `translate(${ringX}px, ${ringY}px) translate(-50%, -50%)`;
        requestAnimationFrame(renderCursor);
    }
    requestAnimationFrame(renderCursor);

    // Magnetic latch on interactive elements
    const interactiveSelectors = 'a, button, .btn, .card-tilt, .school-card-editorial, .creative-category-card, input, select, textarea, .hero-floating-card';
    
    function attachCursorEvents() {
        document.querySelectorAll(interactiveSelectors).forEach(el => {
            if (el.dataset.cursorBound) return;
            el.dataset.cursorBound = 'true';

            el.addEventListener('mouseenter', () => {
                ring.classList.add('active');
            });
            el.addEventListener('mouseleave', () => {
                ring.classList.remove('active');
            });
        });
    }
    attachCursorEvents();

    // Re-check for dynamic elements
    const observer = new MutationObserver(attachCursorEvents);
    observer.observe(document.body, { childList: true, subtree: true });

    // Click pulse wave
    window.addEventListener('mousedown', () => {
        ring.style.transform += ' scale(0.85)';
    });
    window.addEventListener('mouseup', () => {
        ring.style.transform = ring.style.transform.replace(' scale(0.85)', '');
    });
}

// ==================== 2. 3-SECOND CINEMATIC PRELOADER (HOMEPAGE ONLY) ====================
function initPreloader() {
    const preloader = document.getElementById('onbolt-preloader');
    
    // If not on the homepage, immediately reveal content with zero delay
    if (!preloader) {
        document.body.classList.add('site-revealed');
        triggerEntranceAnimations();
        return;
    }

    const progressBar = document.querySelector('.preloader-progress-bar');
    const percentNum = document.querySelector('.preloader-percent-num');
    const statusText = document.querySelector('.preloader-status-text');

    const startTime = performance.now();
    const duration = 3000; // Exact 3.0 Seconds Requirement

    const statusMilestones = [
        { progress: 0.00, text: 'ENGINEERING FABRIC ARCHITECTURE' },
        { progress: 0.28, text: 'SYNCING 300+ PARTNER SCHOOL UNIFORMS' },
        { progress: 0.58, text: 'CALIBRATING 3D ATTIRE & SIZING ENGINE' },
        { progress: 0.88, text: 'ONBOLT COMPLETE BRANDING SOLUTIONS READY' }
    ];

    function updateProgress(now) {
        const elapsed = now - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const percent = Math.floor(progress * 100);

        if (progressBar) {
            progressBar.style.width = percent + '%';
        }
        if (percentNum) {
            percentNum.textContent = String(percent).padStart(2, '0') + '%';
        }

        if (statusText) {
            for (let i = statusMilestones.length - 1; i >= 0; i--) {
                if (progress >= statusMilestones[i].progress) {
                    if (statusText.textContent !== statusMilestones[i].text) {
                        statusText.textContent = statusMilestones[i].text;
                    }
                    break;
                }
            }
        }

        if (progress < 1) {
            requestAnimationFrame(updateProgress);
        } else {
            // Exactly 3.0 seconds reached: Smooth cinematic shutter exit
            setTimeout(() => {
                preloader.classList.add('loaded');
                document.body.classList.add('site-revealed');
                
                // Recalculate ScrollTrigger positions after preloader dismisses
                if (typeof ScrollTrigger !== 'undefined') {
                    ScrollTrigger.refresh();
                }

                // Trigger hero entrance animations
                triggerEntranceAnimations();
            }, 120);
        }
    }

    requestAnimationFrame(updateProgress);
}

// ==================== 3. ENTRANCE ANIMATIONS (GSAP) ====================
function triggerEntranceAnimations() {
    if (typeof gsap === 'undefined') return;

    // Homepage Hero Stagger
    if (document.querySelector('.hero-editorial')) {
        gsap.from('.hero-badge-wrap', { opacity: 0, y: 30, duration: 0.9, ease: 'power3.out' });
        gsap.from('.hero-title', { opacity: 0, y: 40, duration: 1.1, delay: 0.15, ease: 'power3.out' });
        gsap.from('.hero-description', { opacity: 0, y: 25, duration: 1, delay: 0.3, ease: 'power3.out' });
        gsap.from('.hero-cta-group', { opacity: 0, y: 25, duration: 1, delay: 0.45, ease: 'power3.out' });
        gsap.from('.hero-trust-bar', { opacity: 0, y: 25, duration: 1, delay: 0.6, ease: 'power3.out' });
        gsap.from('.hero-floating-card', { opacity: 0, scale: 0.75, duration: 1, delay: 0.75, ease: 'back.out(1.8)', stagger: 0.2 });
    }

    // Subpage Banner Stagger
    const subpageHeader = document.querySelector('div[style*="padding-top: 140px"]');
    if (subpageHeader) {
        gsap.from(subpageHeader.querySelectorAll('.text-eyebrow, h1, p'), {
            opacity: 0,
            y: 30,
            duration: 0.9,
            stagger: 0.15,
            ease: 'power3.out'
        });
    }
}

// ==================== 4. STICKY HEADER ON SCROLL ====================
function initHeaderScroll() {
    const header = document.querySelector('.site-header');
    if (!header) return;

    window.addEventListener('scroll', () => {
        if (window.scrollY > 40) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    }, { passive: true });
}

// ==================== 5. MOBILE MENU TOGGLE & CLICK-OUTSIDE ====================
function initMobileNav() {
    const toggle = document.querySelector('.mobile-nav-toggle');
    const navLinks = document.querySelector('.nav-links');
    if (!toggle || !navLinks) return;

    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = navLinks.classList.toggle('active');
        toggle.classList.toggle('open', isOpen);
    });

    // Close when clicking any nav link
    navLinks.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            navLinks.classList.remove('active');
            toggle.classList.remove('open');
        });
    });

    // Close when clicking outside
    document.addEventListener('click', (e) => {
        if (!navLinks.contains(e.target) && !toggle.contains(e.target)) {
            navLinks.classList.remove('active');
            toggle.classList.remove('open');
        }
    });
}

// ==================== 6. THREE.JS 3D INTERACTIVE HERO EXPERIENCE ====================
function initThreeJsHero() {
    const container = document.getElementById('threejs-hero-canvas');
    if (!container || typeof THREE === 'undefined') return;

    const width = container.clientWidth || 500;
    const height = container.clientHeight || 500;

    // Scene, Camera, WebGL Renderer (100% Borderless & Transparent)
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000);
    camera.position.z = 7.2; // Generous clearance so rings float seamlessly without clipping

    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    renderer.setClearColor(0x000000, 0);
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    container.innerHTML = '';
    container.appendChild(renderer.domElement);

    const mainGroup = new THREE.Group();
    scene.add(mainGroup);

    // Central 3D Multifaceted Bolt Emblem
    const coreGeom = new THREE.IcosahedronGeometry(1.45, 1);
    const coreMat = new THREE.MeshPhysicalMaterial({
        color: 0xFF6702,         // OnBolt Electric Orange
        emissive: 0x331400,
        roughness: 0.18,
        metalness: 0.82,
        clearcoat: 0.9,
        clearcoatRoughness: 0.1,
        wireframe: false
    });
    const coreMesh = new THREE.Mesh(coreGeom, coreMat);
    mainGroup.add(coreMesh);

    // Inner Dark Charcoal Geometric Lattice
    const latticeGeom = new THREE.IcosahedronGeometry(1.25, 0);
    const latticeMat = new THREE.MeshBasicMaterial({
        color: 0x1E2530,        // OnBolt Dark Charcoal
        wireframe: true,
        transparent: true,
        opacity: 0.75
    });
    const latticeMesh = new THREE.Mesh(latticeGeom, latticeMat);
    mainGroup.add(latticeMesh);

    // Outer Speed Rings (Aerodynamic Orbital Cues - Seamlessly Unclipped)
    const ringGeom1 = new THREE.TorusGeometry(2.05, 0.032, 16, 100);
    const ringMat1 = new THREE.MeshStandardMaterial({
        color: 0xFF6702,
        metalness: 0.9,
        roughness: 0.2
    });
    const ringMesh1 = new THREE.Mesh(ringGeom1, ringMat1);
    ringMesh1.rotation.x = Math.PI / 3;
    ringMesh1.rotation.y = Math.PI / 6;
    mainGroup.add(ringMesh1);

    const ringGeom2 = new THREE.TorusGeometry(2.35, 0.022, 16, 100);
    const ringMat2 = new THREE.MeshBasicMaterial({
        color: 0x222222,
        transparent: true,
        opacity: 0.6
    });
    const ringMesh2 = new THREE.Mesh(ringGeom2, ringMat2);
    ringMesh2.rotation.x = -Math.PI / 4;
    mainGroup.add(ringMesh2);

    // Interactive Floating Particle Constellation
    const particleCount = 180;
    const particleGeom = new THREE.BufferGeometry();
    const particlePositions = new Float32Array(particleCount * 3);
    for (let i = 0; i < particleCount * 3; i += 3) {
        particlePositions[i] = (Math.random() - 0.5) * 7.5;
        particlePositions[i + 1] = (Math.random() - 0.5) * 7.5;
        particlePositions[i + 2] = (Math.random() - 0.5) * 5;
    }
    particleGeom.setAttribute('position', new THREE.BufferAttribute(particlePositions, 3));
    const particleMat = new THREE.PointsMaterial({
        color: 0xFF6702,
        size: 0.065,
        transparent: true,
        opacity: 0.8
    });
    const particleSystem = new THREE.Points(particleGeom, particleMat);
    scene.add(particleSystem);

    // Studio Lighting
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.95);
    scene.add(ambientLight);

    const dirLight1 = new THREE.DirectionalLight(0xFF6702, 2.5);
    dirLight1.position.set(5, 5, 4);
    scene.add(dirLight1);

    const dirLight2 = new THREE.DirectionalLight(0x1E2530, 2.0);
    dirLight2.position.set(-5, -5, 3);
    scene.add(dirLight2);

    const pointLight = new THREE.PointLight(0xFF8A3D, 2.8, 12);
    pointLight.position.set(0, 0, 3.5);
    scene.add(pointLight);

    // Resize Handler
    window.addEventListener('resize', () => {
        const w = container.clientWidth || 500;
        const h = container.clientHeight || 500;
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        renderer.setSize(w, h);
    });

    // Pure Autonomous Slow Rotation Loop (Zero Cursor/Mouse Dependency)
    function animate() {
        requestAnimationFrame(animate);

        // Smooth, slow, elegant auto-rotation
        mainGroup.rotation.y += 0.0025;
        mainGroup.rotation.x = Math.sin(Date.now() * 0.0006) * 0.04;

        latticeMesh.rotation.y -= 0.0035;
        ringMesh1.rotation.z += 0.002;
        ringMesh2.rotation.z -= 0.0016;

        particleSystem.rotation.y += 0.0005;

        renderer.render(scene, camera);
    }
    animate();
}

// ==================== 7. 3D PERSPECTIVE TILT WITH REAL-TIME GLARE REFLECTION ====================
function init3DTiltCards() {
    const tiltElements = document.querySelectorAll('.school-card-editorial, .creative-category-card, .product-item-card, .card-tilt');
    
    tiltElements.forEach(card => {
        card.classList.add('card-tilt');

        // Add specular glare overlay if not present
        let glare = card.querySelector('.tilt-glare');
        if (!glare) {
            glare = document.createElement('div');
            glare.className = 'tilt-glare';
            card.appendChild(glare);
        }

        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;

            const rotateX = ((y - centerY) / centerY) * -6.5;
            const rotateY = ((x - centerX) / centerX) * 6.5;

            card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`;

            if (glare) {
                glare.style.opacity = '1';
                glare.style.background = `radial-gradient(circle at ${x}px ${y}px, rgba(255, 255, 255, 0.35) 0%, transparent 60%)`;
            }
        });

        card.addEventListener('mouseleave', () => {
            card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';
            if (glare) {
                glare.style.opacity = '0';
            }
        });
    });
}

// ==================== 8. UNIQUE MOTION & ANIMATION FOR EVERY PAGE ====================
function initPageSpecificAnimations() {
    // 1. Counter Ticker for Stats (About & Home)
    const counters = document.querySelectorAll('.trust-number, .stat-counter');
    if (counters.length > 0 && typeof gsap !== 'undefined') {
        counters.forEach(counter => {
            const text = counter.textContent.trim();
            const numericValue = parseInt(text.replace(/\D/g, ''), 10);
            if (!isNaN(numericValue) && numericValue > 0) {
                const suffix = text.replace(/[0-9]/g, '');
                let obj = { val: 0 };
                gsap.to(obj, {
                    val: numericValue,
                    duration: 2.2,
                    ease: 'power2.out',
                    scrollTrigger: {
                        trigger: counter,
                        start: 'top 90%'
                    },
                    onUpdate: () => {
                        counter.textContent = Math.floor(obj.val) + suffix;
                    }
                });
            }
        });
    }

    // 2. Interactive Instant Search in School Directory
    const schoolSearch = document.getElementById('school-search-box');
    const schoolItems = document.querySelectorAll('.school-item-card');
    const noSchoolNotice = document.getElementById('no-schools-found');

    if (schoolSearch && schoolItems.length > 0) {
        schoolSearch.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            let matched = 0;
            schoolItems.forEach(card => {
                const name = card.getAttribute('data-name') || '';
                const city = card.getAttribute('data-city') || '';
                if (name.includes(query) || city.includes(query)) {
                    card.style.display = 'flex';
                    matched++;
                    if (typeof gsap !== 'undefined') {
                        gsap.fromTo(card, { scale: 0.96, opacity: 0.8 }, { scale: 1, opacity: 1, duration: 0.3 });
                    }
                } else {
                    card.style.display = 'none';
                }
            });
            if (noSchoolNotice) {
                noSchoolNotice.style.display = matched === 0 ? 'block' : 'none';
            }
        });
    }

    // 3. Cart Stepper Quantity Buttons (Cart Page)
    document.querySelectorAll('.cart-qty-stepper').forEach(stepper => {
        const input = stepper.querySelector('input[type="number"]');
        const btnMinus = stepper.querySelector('.btn-qty-minus');
        const btnPlus = stepper.querySelector('.btn-qty-plus');
        const form = stepper.closest('form');

        if (input && btnMinus && btnPlus) {
            btnMinus.addEventListener('click', () => {
                let currentVal = parseInt(input.value, 10) || 1;
                if (currentVal > 1) {
                    input.value = currentVal - 1;
                    if (form) form.submit();
                }
            });
            btnPlus.addEventListener('click', () => {
                let currentVal = parseInt(input.value, 10) || 1;
                input.value = currentVal + 1;
                if (form) form.submit();
            });
        }
    });
}

// ==================== 9. FULLY FUNCTIONAL AJAX FORMS (ENQUIRY & NEWSLETTER) ====================
function initAjaxForms() {
    // 1. Contact & Institutional Proposal Forms
    document.querySelectorAll('.ajax-enquiry-form').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : 'Submit';

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = `
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation: spin 1s linear infinite;">
                        <circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-dashoffset="12"></circle>
                    </svg>
                    <span>Transmitting Proposal...</span>
                `;
            }

            try {
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await response.json();

                if (res.success) {
                    showToast(res.message || 'Inquiry dispatched to OnBolt Institutional Desk!', 'success');
                    form.reset();
                    if (submitBtn) {
                        submitBtn.innerHTML = '✓ Dispatched Successfully!';
                        setTimeout(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }, 3000);
                    }
                } else {
                    showToast(res.error || 'Please fill in all mandatory fields.', 'error');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                }
            } catch (err) {
                showToast('Unable to transmit inquiry. Please call +91 9946925888 directly.', 'error');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }
        });
    });

    // 2. Newsletter Subscription Forms
    document.querySelectorAll('.ajax-newsletter-form').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : 'Join';

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '...';
            }

            try {
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await response.json();

                if (res.success) {
                    showToast(res.message || 'Subscribed to OnBolt updates!', 'success');
                    form.reset();
                } else {
                    showToast(res.error || 'Please enter a valid email address.', 'error');
                }
            } catch (err) {
                showToast('Unable to subscribe. Please try again.', 'error');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }
        });
    });
}

// ==================== 10. INTERACTIVE CART AJAX WITH FLYING PARTICLE EFFECT ====================
function initCartInteractions() {
    document.querySelectorAll('.ajax-add-cart-form').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = `
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation: spin 1s linear infinite;">
                        <circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-dashoffset="12"></circle>
                    </svg>
                    <span>Adding to Kit...</span>
                `;
            }

            const formData = new FormData(form);
            try {
                // Ensure action URL uses current origin and proper path to prevent CORS/relative failures
                let actionUrl = form.getAttribute('action') || form.action || '';
                try {
                    if (actionUrl) {
                        const parsed = new URL(actionUrl, window.location.href);
                        actionUrl = parsed.pathname + parsed.search;
                    } else {
                        actionUrl = (window.location.pathname.includes('/onbolt') ? '/onbolt' : '') + '/api/cart.php';
                    }
                } catch (e) {
                    actionUrl = (window.location.pathname.includes('/onbolt') ? '/onbolt' : '') + '/api/cart.php';
                }

                const response = await fetch(actionUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!response.ok) {
                    throw new Error(`Cart service responded with status ${response.status}`);
                }

                const rawText = await response.text();
                let res;
                try {
                    res = JSON.parse(rawText);
                } catch (parseErr) {
                    console.error('Non-JSON cart response:', rawText);
                    showToast('Server returned an unexpected format.', 'error');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                    return;
                }

                if (res.success) {
                    showToast(res.message || 'Product added to your school kit!', 'success');
                    
                    // Update badges across navbar and buttons
                    document.querySelectorAll('.cart-badge, .cart-badge-text').forEach(b => {
                        b.textContent = res.cartCount;
                        if (typeof gsap !== 'undefined') {
                            gsap.fromTo(b, { scale: 1.6 }, { scale: 1, duration: 0.4, ease: 'back.out(2)' });
                        }
                    });

                    if (submitBtn) {
                        submitBtn.innerHTML = '✓ Added to Requisition!';
                        setTimeout(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }, 2000);
                    }
                } else {
                    showToast(res.error || 'Failed to add item.', 'error');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                }
            } catch (err) {
                console.error('Cart communication error:', err);
                showToast(err.message || 'Unable to connect to cart service.', 'error');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }
        });
    });
}

// ==================== 11. INTERACTIVE SIZING CHART MODAL ====================
function initSizeChartModal() {
    let modal = document.querySelector('.modal-backdrop');
    if (!modal) {
        modal = document.createElement('div');
        modal.className = 'modal-backdrop';
        modal.id = 'size-chart-modal';
        modal.innerHTML = `
            <div class="modal-box">
                <button type="button" class="modal-close-btn" aria-label="Close Modal">&times;</button>
                <div style="display:flex; align-items:center; gap:0.75rem; margin-bottom:1.25rem;">
                    <span style="font-size:1.8rem;">📐</span>
                    <div>
                        <h3 class="heading-2" style="font-size:1.4rem;">Official Uniform Size Guide</h3>
                        <p style="font-size:0.85rem; color:var(--brand-muted);">Approved standard measurements for OnBolt academic garments.</p>
                    </div>
                </div>
                <div style="overflow-x:auto; margin-bottom:1.5rem;">
                    <table style="width:100%; border-collapse:collapse; font-size:0.9rem; text-align:left;">
                        <thead>
                            <tr style="background:var(--brand-surface-alt); border-bottom:2px solid var(--brand-border); color:var(--brand-dark); font-weight:800;">
                                <th style="padding:0.75rem 1rem;">Size</th>
                                <th style="padding:0.75rem 1rem;">Recommended Class / Age</th>
                                <th style="padding:0.75rem 1rem;">Chest (Inches)</th>
                                <th style="padding:0.75rem 1rem;">Shirt Length</th>
                                <th style="padding:0.75rem 1rem;">Waist (Pants)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="border-bottom:1px solid var(--brand-border);"><td style="padding:0.65rem 1rem; font-weight:800; color:var(--brand-primary);">Size 28</td><td style="padding:0.65rem 1rem;">KG to Class 2 (Ages 5–6)</td><td style="padding:0.65rem 1rem;">28"</td><td style="padding:0.65rem 1rem;">20"</td><td style="padding:0.65rem 1rem;">22"–24"</td></tr>
                            <tr style="border-bottom:1px solid var(--brand-border);"><td style="padding:0.65rem 1rem; font-weight:800; color:var(--brand-primary);">Size 30</td><td style="padding:0.65rem 1rem;">Class 3–4 (Ages 7–8)</td><td style="padding:0.65rem 1rem;">30"</td><td style="padding:0.65rem 1rem;">22"</td><td style="padding:0.65rem 1rem;">24"–26"</td></tr>
                            <tr style="border-bottom:1px solid var(--brand-border);"><td style="padding:0.65rem 1rem; font-weight:800; color:var(--brand-primary);">Size 32</td><td style="padding:0.65rem 1rem;">Class 5–6 (Ages 9–10)</td><td style="padding:0.65rem 1rem;">32"</td><td style="padding:0.65rem 1rem;">24"</td><td style="padding:0.65rem 1rem;">26"–28"</td></tr>
                            <tr style="border-bottom:1px solid var(--brand-border);"><td style="padding:0.65rem 1rem; font-weight:800; color:var(--brand-primary);">Size 34</td><td style="padding:0.65rem 1rem;">Class 7–8 (Ages 11–12)</td><td style="padding:0.65rem 1rem;">34"</td><td style="padding:0.65rem 1rem;">26"</td><td style="padding:0.65rem 1rem;">28"–30"</td></tr>
                            <tr style="border-bottom:1px solid var(--brand-border);"><td style="padding:0.65rem 1rem; font-weight:800; color:var(--brand-primary);">Size 36</td><td style="padding:0.65rem 1rem;">Class 9–10 (Ages 13–14)</td><td style="padding:0.65rem 1rem;">36"</td><td style="padding:0.65rem 1rem;">28"</td><td style="padding:0.65rem 1rem;">30"–32"</td></tr>
                            <tr><td style="padding:0.65rem 1rem; font-weight:800; color:var(--brand-primary);">Size 38+</td><td style="padding:0.65rem 1rem;">Class 11–12 &amp; Senior</td><td style="padding:0.65rem 1rem;">38"–40"</td><td style="padding:0.65rem 1rem;">30"</td><td style="padding:0.65rem 1rem;">32"–34"</td></tr>
                        </tbody>
                    </table>
                </div>
                <div style="background:var(--brand-primary-light); border-radius:var(--radius-md); padding:1rem; font-size:0.85rem; color:var(--brand-dark); line-height:1.5;">
                    💡 <strong>Growth Guarantee</strong>: All OnBolt academic uniforms include 2-inch let-out allowance in seams and elasticized waistbands for growing students.
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        modal.querySelector('.modal-close-btn').addEventListener('click', () => {
            modal.classList.remove('open');
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('open');
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('open')) {
                modal.classList.remove('open');
            }
        });
    }

    // Attach to triggers
    document.querySelectorAll('.open-size-chart-btn, a[href="#size-chart-modal"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            modal.classList.add('open');
        });
    });
}

// ==================== 12. GSAP SCROLL TRIGGER ANIMATIONS ====================
function initAnimations() {
    if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    gsap.registerPlugin(ScrollTrigger);

    // Staggered reveals for Category Cards (ClearProps ensures hover & layout never break)
    if (document.querySelector('.category-cards-grid')) {
        gsap.fromTo('.creative-category-card', 
            { y: 35, opacity: 0.35 },
            {
                y: 0,
                opacity: 1,
                stagger: 0.12,
                duration: 0.7,
                ease: 'power3.out',
                clearProps: 'transform,opacity',
                scrollTrigger: {
                    trigger: '.category-cards-grid',
                    start: 'top 95%',
                    once: true
                }
            }
        );
    }

    // Staggered reveals for School Cards (ClearProps ensures hover & layout never break)
    if (document.querySelector('.schools-directory-grid')) {
        gsap.fromTo('.school-card-editorial', 
            { y: 35, opacity: 0.35 },
            {
                y: 0,
                opacity: 1,
                stagger: 0.12,
                duration: 0.7,
                ease: 'power3.out',
                clearProps: 'transform,opacity',
                scrollTrigger: {
                    trigger: '.schools-directory-grid',
                    start: 'top 95%',
                    once: true
                }
            }
        );
    }

    // Refresh triggers on full window load
    window.addEventListener('load', () => {
        ScrollTrigger.refresh();
    });
}

// ==================== 13. SMOOTH SCROLL FOR ANCHORS ====================
function initSmoothScrollButtons() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#' || targetId === '#!' || targetId === '') return;
            const target = document.querySelector(targetId);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
}

// ==================== 14. TOAST NOTIFICATION UTILITY ====================
function showToast(message, type = 'info') {
    let toast = document.querySelector('.toast-notice');
    if (!toast) {
        toast = document.createElement('div');
        toast.className = 'toast-notice';
        document.body.appendChild(toast);
    }

    const icon = type === 'success' ? '✓' : 'ℹ';
    toast.innerHTML = `<span style="font-weight:900;color:var(--brand-primary);">${icon}</span> <span>${message}</span>`;
    toast.classList.add('show');

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3500);
}

// Keyframes helper for spinner
const styleSheet = document.createElement('style');
styleSheet.textContent = `@keyframes spin { 100% { transform: rotate(360deg); } }`;
document.head.appendChild(styleSheet);
