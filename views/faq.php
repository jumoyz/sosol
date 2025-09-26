<?php
// Set page title
$pageTitle = "Frequently Asked Questions";
?>
<div class="faq-bg position-fixed top-0 start-0 w-100 h-100" style="z-index:-1;"></div>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="glass-card shadow-lg border-0 mb-4">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <img src="/public/images/sosol-logo.jpg" alt="SOSOL Logo" height="48" class="mb-3 rounded-circle shadow-sm">
                        <h2 class="fw-bold text-gradient mb-1">Frequently Asked Questions</h2>
                        <p class="text-muted">Find answers to common questions about SoSol</p>
                    </div>
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq1">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1" aria-expanded="true" aria-controls="collapse1">
                                    What is SoSol and how does it work?
                                </button>
                            </h2>
                            <div id="collapse1" class="accordion-collapse collapse show" aria-labelledby="faq1" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    SoSol is a digital platform for managing rotating savings and credit groups (ROSCAs). Members contribute regularly and take turns receiving payouts, making saving and lending easy, transparent, and social.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq2">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2" aria-expanded="false" aria-controls="collapse2">
                                    How do I join a SOL group?
                                </button>
                            </h2>
                            <div id="collapse2" class="accordion-collapse collapse" aria-labelledby="faq2" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    You can join a public SOL group directly or accept an invitation to a private group. Go to the "Groups" page, browse available groups, and click "Join" or accept an invite link from an admin or manager.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq3">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3" aria-expanded="false" aria-controls="collapse3">
                                    How do contributions and payouts work?
                                </button>
                            </h2>
                            <div id="collapse3" class="accordion-collapse collapse" aria-labelledby="faq3" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Each member contributes a fixed amount per cycle. On each cycle, one member receives the total payout. The order is determined by the group admin or by draw, and the process repeats until all cycles are complete.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq4">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4" aria-expanded="false" aria-controls="collapse4">
                                    What payment methods are supported?
                                </button>
                            </h2>
                            <div id="collapse4" class="accordion-collapse collapse" aria-labelledby="faq4" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    You can contribute using your SoSol wallet, bank transfer, mobile money, or cash (with admin approval for non-wallet payments).
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq5">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse5" aria-expanded="false" aria-controls="collapse5">
                                    Is my money safe on SoSol?
                                </button>
                            </h2>
                            <div id="collapse5" class="accordion-collapse collapse" aria-labelledby="faq5" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    SoSol uses secure payment gateways and bank-level encryption. Group admins and managers oversee group activity, and all transactions are logged for transparency.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq6">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse6" aria-expanded="false" aria-controls="collapse6">
                                    Who can I contact for support?
                                </button>
                            </h2>
                            <div id="collapse6" class="accordion-collapse collapse" aria-labelledby="faq6" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    If you need help, use the "Contact Us" page or email support@sosol.com. Our team is here to assist you with any questions or issues.
                                </div>
                            </div>
                        </div>
                               <div class="accordion-item">
                            <h2 class="accordion-header" id="faq6">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse6" aria-expanded="false" aria-controls="collapse6">
                                    What is a ROSCA?
                                </button>
                            </h2>
                            <div id="collapse6" class="accordion-collapse collapse" aria-labelledby="faq6" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    A rotating savings and credit association (ROSCA) is a group of individuals who agree to meet for a defined period in order to save and borrow together, a form of combined peer-to-peer banking and peer-to-peer lending. Members all chip in regularly and take turns withdrawing accumulated sums.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
.faq-bg {
    background: linear-gradient(120deg, #007bff 60%, #6f42c1 100%);
    opacity: 0.10;
}
.glass-card {
    background: rgba(255,255,255,0.18);
    border-radius: 1.2rem;
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.18);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.18);
}
.text-gradient {
    background: linear-gradient(90deg, #007bff 40%, #6f42c1 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    color: #007bff;
}
.accordion-button:focus {
    box-shadow: 0 0 0 0.15rem rgba(111,66,193,.15);
    border-color: #6f42c1;
}
</style>
