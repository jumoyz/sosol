<?php 
/**
 * Home page
 */
$pageTitle = "Home";
?>
<div class="hero-section position-relative text-white py-5" style="background: linear-gradient(120deg, #007bff 60%, #6f42c1 100%); overflow:hidden;">
    <div class="container position-relative">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-5 mb-lg-0">
                <div class="glass-card p-4 mb-4">
                    <h1 class="display-4 fw-bold mb-3 animate__animated animate__fadeInDown"><?= htmlspecialchars(__t('home_hero_title')) ?></h1>
                    <p class="lead mb-4 animate__animated animate__fadeInUp animate__delay-1s"><?= htmlspecialchars(__t('home_hero_desc')) ?></p>
                    <div class="d-flex gap-3">
                        <a href="?page=register" class="btn btn-light btn-lg fw-bold shadow-sm px-4 py-2 animate__animated animate__fadeInLeft animate__delay-2s"><?= htmlspecialchars(__t('get_started')) ?></a>
                        <a href="?page=how-it-works" class="btn btn-outline-light btn-lg fw-bold shadow-sm px-4 py-2 animate__animated animate__fadeInRight animate__delay-2s"><?= htmlspecialchars(__t('learn_more')) ?></a>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <img src="http://sosol.local/public/images/hero-image.png" alt="Financial Community" class="img-fluid rounded-4 shadow-lg hero-img-animate">
            </div>
        </div>
        <div class="hero-bg-glass"></div>
    </div>
</div>

<div class="features-section py-5 bg-light position-relative">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold"><?= htmlspecialchars(__t('home_how_it_works_title')) ?></h2>
            <p class="lead text-muted"><?= htmlspecialchars(__t('home_how_it_works_desc')) ?></p>
        </div>

    <div class="row g-4">
            <div class="col-md-3">
                <div class="card h-100 border-0 shadow feature-card">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-primary text-white rounded-circle mb-3 mx-auto animate__animated animate__pulse animate__infinite">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                        <h4 class="fw-bold"><?= htmlspecialchars(__t('home_how_it_works_feature_sol')) ?></h4>
                        <p class="text-muted"><?= htmlspecialchars(__t('home_how_it_works_feature_sol_desc')) ?></p>
                        <a href="?page=how-it-works#sol" class="btn btn-sm btn-outline-primary mt-2"><?= htmlspecialchars(__t('learn_more')) ?></a>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card h-100 border-0 shadow feature-card">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-primary text-white rounded-circle mb-3 mx-auto animate__animated animate__pulse animate__infinite">
                            <i class="fas fa-handshake fa-2x"></i>
                        </div>
                        <h4 class="fw-bold"><?= htmlspecialchars(__t('home_how_it_works_feature_lending')) ?></h4>
                        <p class="text-muted"><?= htmlspecialchars(__t('home_how_it_works_feature_lending_desc')) ?></p>
                        <a href="?page=how-it-works#lending" class="btn btn-sm btn-outline-primary mt-2"><?= htmlspecialchars(__t('learn_more')) ?></a>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card h-100 border-0 shadow feature-card">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-primary text-white rounded-circle mb-3 mx-auto animate__animated animate__pulse animate__infinite">
                            <i class="fas fa-seedling fa-2x"></i>
                        </div>
                        <h4 class="fw-bold"><?= htmlspecialchars(__t('home_how_it_works_feature_crowdfunding')) ?></h4>
                        <p class="text-muted"><?= htmlspecialchars(__t('home_how_it_works_feature_crowdfunding_desc')) ?></p>
                        <a href="?page=how-it-works#crowdfunding" class="btn btn-sm btn-outline-primary mt-2"><?= htmlspecialchars(__t('learn_more')) ?></a>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card h-100 border-0 shadow feature-card">
                    <div class="card-body text-center p-4">
                        <div class="feature-icon bg-primary text-white rounded-circle mb-3 mx-auto animate__animated animate__pulse animate__infinite">
                            <i class="fas fa-wallet fa-2x"></i>
                        </div>
                        <h4 class="fw-bold"><?= htmlspecialchars(__t('home_how_it_works_feature_wallet')) ?></h4>
                        <p class="text-muted"><?= htmlspecialchars(__t('home_how_it_works_feature_wallet_desc')) ?></p>
                        <a href="?page=how-it-works#sol" class="btn btn-sm btn-outline-primary mt-2"><?= htmlspecialchars(__t('learn_more')) ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="testimonial-section py-5 position-relative">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <h2 class="fw-bold mb-4"><?= htmlspecialchars(__t('home_testimonial_section')) ?></h2>
                
                <div class="testimonial-carousel position-relative">
                    <div class="card border-0 bg-white shadow-sm p-4 mb-4 testimonial-card animate__animated animate__fadeIn" id="testimonial-0">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="testimonial-avatar">
                                <img src="http://sosol.local/public/images/testimonial.png" alt="Marie" class="rounded-circle" width="80">
                            </div>
                        </div>
                        <p class="mb-3">"<?= htmlspecialchars(__t('home_testimonial1_content')) ?>"</p>
                        <h5 class="mb-0"><?= htmlspecialchars(__t('home_testimonial1_author')) ?></h5>
                        <p class="text-muted small"><?= htmlspecialchars(__t('home_testimonial1_extra')) ?></p>
                    </div>
                    <div class="card border-0 bg-white shadow-sm p-4 mb-4 testimonial-card d-none animate__animated animate__fadeIn" id="testimonial-1">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="testimonial-avatar">
                                <img src="http://sosol.local/public/images/testimonial.png" alt="Jean" class="rounded-circle" width="80">
                            </div>
                        </div>
                        <p class="mb-3">"<?= htmlspecialchars(__t('home_testimonial2_content')) ?>"</p>
                        <h5 class="mb-0"><?= htmlspecialchars(__t('home_testimonial2_author')) ?></h5>
                        <p class="text-muted small"><?= htmlspecialchars(__t('home_testimonial2_extra')) ?></p>
                    </div>
                </div>
                <div class="testimonial-nav mt-3">
                    <span class="testimonial-dot active" data-index="0"></span>
                    <span class="testimonial-dot" data-index="1"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="stats-section py-5 bg-primary text-white">
    <div class="container">
        <div class="row text-center g-4">
            <div class="col-md-4">
                <h2 class="display-4 fw-bold mb-0">1,500+</h2>
                <p><?= htmlspecialchars(__t('active_members')) ?></p>
            </div>
            <div class="col-md-4">
                <h2 class="display-4 fw-bold mb-0">250+</h2>
                <p><?= htmlspecialchars(__t('sol_group_formed')) ?></p>
            </div>
            <div class="col-md-4">
                <h2 class="display-4 fw-bold mb-0">HTG 5M+</h2>
                <p><?= htmlspecialchars(__t('funds_mobilized')) ?></p>
            </div>
        </div>
    </div>
</div>

<div class="cta-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow bg-light p-5 text-center">
                    <h2 class="mb-4"><?= htmlspecialchars(__t('join_cta_title')) ?></h2>
                    <p class="lead mb-4"><?= htmlspecialchars(__t('join_cta_desc')) ?></p>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="?page=register" class="btn btn-primary btn-lg"><?= htmlspecialchars(__t('create_account')) ?></a>
                        <a href="?page=login" class="btn btn-outline-secondary btn-lg"><?= htmlspecialchars(__t('sign_in')) ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="faq-section py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center mb-5">
            <div class="col-lg-8 text-center">
                <h2 class="fw-bold"><?= htmlspecialchars(__t('frequently_asked_questions')) ?></h2>
                <p class="text-muted"><?= htmlspecialchars(__t('faq_desc')) ?></p>
            </div>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                <?= htmlspecialchars(__t('faq_q2')) ?>
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                <?= htmlspecialchars(__t('faq_a2')) ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                <?= htmlspecialchars(__t('faq_q3')) ?>
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                <?= htmlspecialchars(__t('faq_a3')) ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                <?= htmlspecialchars(__t('faq_q4')) ?>
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                <?= htmlspecialchars(__t('faq_a4')) ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <a href="?page=faq" class="btn btn-outline-primary"><?= htmlspecialchars(__t('faq_cta')) ?></a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mission & Values Section -->
<div class="mission-section py-5 bg-white">
    <div class="container">
        <div class="row justify-content-center mb-4">
            <div class="col-lg-8 text-center">
                <h2 class="fw-bold text-gradient mb-3">Our Mission & Values</h2>
                <p class="lead text-muted">Empowering communities through financial inclusion, trust, and innovation. SOSOL is built on transparency, collaboration, and a passion for positive change.</p>
            </div>
        </div>
        <div class="row text-center g-4">
            <div class="col-md-4">
                <div class="value-icon mb-3"><i class="fas fa-heart text-danger fa-2x"></i></div>
                <h5 class="fw-bold">Community First</h5>
                <p class="text-muted">We put people at the center, fostering support and shared growth.</p>
            </div>
            <div class="col-md-4">
                <div class="value-icon mb-3"><i class="fas fa-shield-alt text-primary fa-2x"></i></div>
                <h5 class="fw-bold">Trust & Security</h5>
                <p class="text-muted">Your safety and privacy are our top priorities, always.</p>
            </div>
            <div class="col-md-4">
                <div class="value-icon mb-3"><i class="fas fa-lightbulb text-warning fa-2x"></i></div>
                <h5 class="fw-bold">Innovation</h5>
                <p class="text-muted">We embrace new ideas to make finance accessible and simple for all.</p>
            </div>
        </div>
    </div>
</div>

<!-- Partners & Media Section -->
<div class="partners-section py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center mb-4">
            <div class="col-lg-8 text-center">
                <h2 class="fw-bold text-gradient mb-3">Trusted By</h2>
                <p class="text-muted">SOSOL is proud to partner with leading organizations and be featured in respected media outlets.</p>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="d-flex flex-wrap justify-content-center align-items-center gap-4 partners-logos">
                    <img src="http://sosol.local/public/images/partner-avatar.png" alt="Partner 1" height="40">
                    <img src="http://sosol.local/public/images/partner-avatar.png" alt="Partner 2" height="40">
                    <img src="http://sosol.local/public/images/partner-avatar.png" alt="Partner 3" height="40">
                    <img src="http://sosol.local/public/images/partner-avatar.png" alt="Media 1" height="40">
                    <img src="http://sosol.local/public/images/partner-avatar.png" alt="Media 2" height="40">
                </div>
            </div>
        </div>
    </div>
</div>

<div class="app-section py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <img src="http://sosol.local/public/images/mobile-app.jpeg" alt="SoSol Mobile App" class="img-fluid rounded-4 shadow">
            </div>
            <div class="col-lg-6">
                <h2 class="fw-bold mb-4"><?= htmlspecialchars(__t('app_cta_title')) ?></h2>
                <p class="lead mb-4"><?= htmlspecialchars(__t('app_cta_desc')) ?></p>
                <div class="d-flex gap-3">
                    <a href="#" class="btn btn-dark">
                        <i class="fab fa-google-play me-2"></i> Google Play
                    </a>
                    <a href="#" class="btn btn-dark">
                        <i class="fab fa-apple me-2"></i> App Store
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"></script>
<script>
// Modern testimonial carousel with auto-slide
document.addEventListener('DOMContentLoaded', function() {
    const dots = document.querySelectorAll('.testimonial-dot');
    const testimonials = document.querySelectorAll('.testimonial-card');
    let current = 0;
    let interval;

    function showTestimonial(idx) {
        testimonials.forEach((t, i) => {
            t.classList.toggle('d-none', i !== idx);
        });
        dots.forEach((d, i) => {
            d.classList.toggle('active', i === idx);
        });
        current = idx;
    }

    function nextTestimonial() {
        let next = (current + 1) % testimonials.length;
        showTestimonial(next);
    }

    dots.forEach(dot => {
        dot.addEventListener('click', () => {
            showTestimonial(Number(dot.getAttribute('data-index')));
            clearInterval(interval);
            interval = setInterval(nextTestimonial, 5000);
        });
    });

    showTestimonial(0);
    interval = setInterval(nextTestimonial, 5000);
});
</script>

<style>
/* Branding Tweaks */
.text-gradient {
    background: linear-gradient(90deg, #007bff 40%, #6f42c1 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    color: #007bff;
}
.value-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    background: rgba(0,123,255,0.08);
    border-radius: 50%;
}
.partners-logos img {
    filter: grayscale(60%) brightness(1.1);
    opacity: 0.8;
    transition: filter 0.2s, opacity 0.2s;
}
.partners-logos img:hover {
    filter: none;
    opacity: 1;
}
.mission-section, .partners-section {
    font-family: 'Segoe UI', 'Roboto', Arial, sans-serif;
}
.mission-section h2, .partners-section h2 {
    letter-spacing: 1px;
    font-size: 2.2rem;
}
.mission-section h5 {
    color: #6f42c1;
}
.partners-section {
    border-top: 1px solid #eee;
}
/* ...existing code... */
/* Glassmorphism for hero and CTA */
.glass-card {
    background: rgba(255,255,255,0.15);
    border-radius: 1rem;
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.18);
}
.hero-bg-glass {
    position: absolute;
    top: 0; left: 60%; right: 0; bottom: 0;
    background: rgba(255,255,255,0.08);
    border-radius: 0 0 0 80px;
    z-index: 0;
    pointer-events: none;
}
.hero-img-animate {
    animation: floatY 3s ease-in-out infinite alternate;
}
@keyframes floatY {
    0% { transform: translateY(0); }
    100% { transform: translateY(-20px); }
}

/* Feature cards */
.feature-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.feature-card:hover {
    transform: translateY(-8px) scale(1.03);
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
}
.feature-icon {
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: background 0.3s;
}
.feature-card:hover .feature-icon {
    background: linear-gradient(120deg, #007bff 60%, #6f42c1 100%);
}

/* Testimonial carousel */
.testimonial-dot {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: #dee2e6;
    margin: 0 5px;
    cursor: pointer;
    transition: background 0.3s;
}
.testimonial-dot.active {
    background-color: #007bff;
}
.testimonial-card {
    transition: box-shadow 0.2s, transform 0.2s;
}
.testimonial-card:hover {
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
    transform: scale(1.02);
}

/* Stats section */
.stats-section {
    background: linear-gradient(120deg, #007bff 60%, #6f42c1 100%);
}

/* CTA card */
.cta-section .card {
    background: rgba(255,255,255,0.7);
    border-radius: 1rem;
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.10);
    backdrop-filter: blur(4px);
}

/* App section */
.app-section .card, .app-section img {
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
}

@media (max-width: 991px) {
    .hero-section .row { flex-direction: column-reverse; }
    .hero-section .col-lg-6 { text-align: center; }
    .hero-section img { margin-bottom: 2rem; }
}
</style>