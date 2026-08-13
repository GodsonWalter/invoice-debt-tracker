@extends('layouts.public')

@php($platform = $platformSettings['settings'])

@section('meta_title', $platform->seo_title ?: $platform->product_title)
@section('meta_description', $platform->seo_description ?: $platform->tagline)

@section('content')
    @php($homepageCurrencySymbol = $homepageCurrency['symbol'] ?? '$')

    <header class="site-header">
        <nav class="navbar navbar-expand-lg" aria-label="Primary navigation">
            <div class="container">
                <a class="brand-link align-items-center d-inline-flex fw-bold text-decoration-none" href="{{ route('home') }}"
                    aria-label="{{ $platform->product_name }} home">
                    @if ($platformSettings['logoUrl'])<img src="{{ $platformSettings['logoUrl'] }}" alt="{{ $platform->product_name }}" style="max-width:132px;height:36px;object-fit:contain">@else<span class="brand-mark" aria-hidden="true"><i class="bi bi-receipt-cutoff"></i></span>@endif
                    <span>{{ $platform->product_name }}</span>
                </a>

                <button class="navbar-toggler border-0 p-2" type="button" data-bs-toggle="collapse"
                    data-bs-target="#publicNavigation" aria-controls="publicNavigation" aria-expanded="false"
                    aria-label="Toggle navigation">
                    <i class="bi bi-list fs-3 text-dark" aria-hidden="true"></i>
                </button>

                <div class="collapse navbar-collapse" id="publicNavigation">
                    <ul class="navbar-nav public-nav mx-auto my-3 my-lg-0">
                        <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                        <li class="nav-item"><a class="nav-link" href="#how-it-works">How It Works</a></li>
                        <li class="nav-item"><a class="nav-link" href="#testimonials">Testimonials</a></li>
                        <li class="nav-item"><a class="nav-link" href="#pricing">Pricing</a></li>
                        <li class="nav-item"><a class="nav-link" href="#faqs">FAQs</a></li>
                        <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                    </ul>

                    <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2">
                        <x-theme-toggle class="public-theme-toggle" />

                        @auth
                            <a href="{{ route('dashboard') }}" class="btn btn-primary px-4">Open Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-outline-primary px-4">Sign In</a>
                            <a href="{{ route('register') }}" class="btn btn-primary px-4">Get Started Free</a>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <section class="hero-section" id="home">
            <div class="container">
                <div class="row align-items-center g-5">
                    <div class="col-lg-6">
                        <p class="eyebrow">{{ $platform->tagline ?: 'Invoice clarity for growing businesses' }}</p>
                        <h1 class="hero-heading mb-4">
                            Create Invoices. <span class="accent">Track Payments.</span> Recover Debts Faster.
                        </h1>
                        <p class="hero-copy mb-4">
                            Create professional invoices, monitor payments, automate reminders, and manage customer debts
                            from one secure workspace.
                        </p>
                        <div class="hero-actions d-flex flex-column flex-sm-row gap-3">
                            @guest
                                <a href="{{ route('register') }}" class="btn btn-primary btn-lg px-4">Get Started Free <i
                                        class="bi bi-arrow-up-right ms-1" aria-hidden="true"></i></a>
                            @else
                                <a href="{{ route('dashboard') }}" class="btn btn-primary btn-lg px-4">Open Dashboard <i
                                        class="bi bi-arrow-up-right ms-1" aria-hidden="true"></i></a>
                            @endguest
                            <a href="#how-it-works" class="btn btn-outline-primary btn-lg px-4">See How It Works <i
                                    class="bi bi-arrow-down ms-1" aria-hidden="true"></i></a>
                        </div>
                        <p class="hero-note mt-4 mb-0"><i class="bi bi-check-circle-fill text-success me-1"
                                aria-hidden="true"></i> A focused workspace for invoicing, collections, and follow-up.</p>
                    </div>

                    <div class="col-lg-6">
                        <div class="dashboard-preview" role="region" aria-label="Sample {{ $platform->product_name }} dashboard preview">
                            <div class="preview-toolbar">
                                <div class="preview-dots" aria-hidden="true"><span></span><span></span><span></span></div>
                                <span class="preview-label">{{ $platform->product_name }} workspace</span>
                                <span class="sample-badge">Sample data</span>
                            </div>
                            <div class="preview-content">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div>
                                        <div class="preview-heading">Business overview</div>
                                        <div class="preview-muted">A clear view of current cash flow</div>
                                    </div>
                                    <i class="bi bi-three-dots text-secondary" aria-hidden="true"></i>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6 col-xl-3">
                                        <div class="preview-summary">
                                            <div class="label mb-2">Revenue</div>
                                            <div class="value">{{ $homepageCurrencySymbol }}1.28m</div>
                                            <div class="trend mt-1"><i class="bi bi-arrow-up-right" aria-hidden="true"></i> 12.4%</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-xl-3">
                                        <div class="preview-summary">
                                            <div class="label mb-2">Outstanding debt</div>
                                            <div class="value">{{ $homepageCurrencySymbol }}486k</div>
                                            <div class="trend mt-1"><i class="bi bi-activity" aria-hidden="true"></i> Current</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-xl-3">
                                        <div class="preview-summary">
                                            <div class="label mb-2">Overdue invoices</div>
                                            <div class="value">18</div>
                                            <div class="text-danger preview-muted mt-1">Needs follow-up</div>
                                        </div>
                                    </div>
                                    <div class="col-6 col-xl-3">
                                        <div class="preview-summary">
                                            <div class="label mb-2">Recent payments</div>
                                            <div class="value">{{ $homepageCurrencySymbol }}245k</div>
                                            <div class="trend mt-1"><i class="bi bi-check2-circle" aria-hidden="true"></i> This week</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-2">
                                    <div class="col-md-7">
                                        <div class="preview-panel">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="preview-heading">Revenue chart</div>
                                                <span class="preview-muted">Jan – Jun</span>
                                            </div>
                                            <div class="preview-chart">
                                                <svg viewBox="0 0 600 180" role="img" aria-label="Sample revenue chart rising from January to June">
                                                    <line x1="16" y1="152" x2="584" y2="152" stroke="#e5ebf3" stroke-width="2" />
                                                    <line x1="16" y1="96" x2="584" y2="96" stroke="#edf1f6" stroke-width="2" />
                                                    <line x1="16" y1="40" x2="584" y2="40" stroke="#edf1f6" stroke-width="2" />
                                                    <polygon points="16,140 126,122 236,133 346,82 456,91 584,35 584,152 16,152" fill="#eff5ff" />
                                                    <polyline points="16,140 126,122 236,133 346,82 456,91 584,35" fill="none" stroke="#2563eb" stroke-linecap="round" stroke-linejoin="round" stroke-width="5" />
                                                    <circle cx="16" cy="140" r="5" fill="#2563eb" />
                                                    <circle cx="126" cy="122" r="5" fill="#2563eb" />
                                                    <circle cx="236" cy="133" r="5" fill="#2563eb" />
                                                    <circle cx="346" cy="82" r="5" fill="#2563eb" />
                                                    <circle cx="456" cy="91" r="5" fill="#2563eb" />
                                                    <circle cx="584" cy="35" r="5" fill="#0f9f8f" />
                                                </svg>
                                            </div>
                                            <div class="preview-chart-labels"><span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span><span>Jun</span></div>
                                        </div>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="preview-panel">
                                            <div class="preview-heading">Recent customer payments</div>
                                            <div class="preview-payments">
                                                <div class="preview-payment">
                                                    <span class="avatar" aria-hidden="true">GS</span>
                                                    <span class="flex-grow-1"><span class="name">Grace Stores</span><span class="meta">INV-2026-0041</span></span>
                                                    <span class="amount">{{ $homepageCurrencySymbol }}120k</span>
                                                </div>
                                                <div class="preview-payment">
                                                    <span class="avatar" aria-hidden="true">MV</span>
                                                    <span class="flex-grow-1"><span class="name">Midas Ventures</span><span class="meta">INV-2026-0038</span></span>
                                                    <span class="amount">{{ $homepageCurrencySymbol }}80k</span>
                                                </div>
                                                <div class="preview-payment">
                                                    <span class="avatar" aria-hidden="true">AC</span>
                                                    <span class="flex-grow-1"><span class="name">Ayo Catering</span><span class="meta">INV-2026-0035</span></span>
                                                    <span class="amount">{{ $homepageCurrencySymbol }}45k</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-space" id="features">
            <div class="container">
                <div class="row justify-content-between align-items-end g-4 mb-5">
                    <div class="col-lg-7">
                        <p class="eyebrow">Built for the full invoice cycle</p>
                        <h2 class="section-heading mb-3">Everything you need to manage invoices and debts</h2>
                    </div>
                    <div class="col-lg-4">
                        <p class="section-lead mb-0">Bring customer records, invoices, payments, reminders, and reports into one practical workspace.</p>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6 col-lg-4">
                        <article class="feature-card">
                            <span class="feature-icon"><i class="bi bi-receipt-cutoff" aria-hidden="true"></i></span>
                            <h3>Professional Invoicing</h3>
                            <p>Create clear, branded invoices and keep each customer’s billing history organized.</p>
                        </article>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <article class="feature-card">
                            <span class="feature-icon"><i class="bi bi-wallet2" aria-hidden="true"></i></span>
                            <h3>Payment Tracking</h3>
                            <p>Record payments, monitor balances, and see what is still outstanding at a glance.</p>
                        </article>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <article class="feature-card">
                            <span class="feature-icon"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></span>
                            <h3>Debt Management</h3>
                            <p>Identify overdue balances quickly and prioritize follow-up from one workspace.</p>
                        </article>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <article class="feature-card">
                            <span class="feature-icon"><i class="bi bi-bell" aria-hidden="true"></i></span>
                            <h3>Automated Reminders</h3>
                            <p>Use reminder schedules and reusable email templates to make follow-up consistent.</p>
                        </article>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <article class="feature-card">
                            <span class="feature-icon"><i class="bi bi-chat-dots" aria-hidden="true"></i></span>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <h3 class="mb-0">WhatsApp &amp; SMS</h3>
                                <span class="roadmap-badge">Roadmap</span>
                            </div>
                            <p>Keep customer follow-up organized as WhatsApp and SMS workflows are added to {{ $platform->product_name }}.</p>
                        </article>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <article class="feature-card">
                            <span class="feature-icon"><i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i></span>
                            <h3>Reports &amp; Exports</h3>
                            <p>Review revenue, outstanding balances, overdue invoices, and export workspace-scoped reports.</p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-space section-surface" id="testimonials" aria-labelledby="homepage-testimonials-heading">
            <div class="container">
                <div class="text-center mx-auto mb-5" style="max-width: 700px;">
                    <p class="eyebrow">Customer stories</p>
                    <h2 id="homepage-testimonials-heading" class="section-heading mb-3">Trusted by teams using {{ $platform->product_name }}</h2>
                    <p class="section-lead mx-auto mb-0">See how businesses are bringing their invoicing and payment follow-up into focus.</p>
                </div>

                @if (isset($testimonials) && $testimonials->isNotEmpty())
                    <div class="row g-4">
                        @foreach ($testimonials as $testimonial)
                            <div class="col-md-6 col-lg-4">
                                <article class="feature-card testimonial-card h-100">
                                    <header class="testimonial-header d-flex align-items-center">
                                        <div class="testimonial-author d-flex align-items-center gap-3">
                                            @if ($testimonial->image_url)
                                                <img src="{{ $testimonial->image_url }}" alt="{{ $testimonial->display_name }}" class="testimonial-avatar rounded-circle object-fit-cover">
                                            @else
                                                <span class="testimonial-avatar testimonial-avatar-fallback" aria-hidden="true">
                                                    <i class="bi bi-person-fill"></i>
                                                </span>
                                            @endif

                                            <div>
                                                <cite class="testimonial-name">{{ $testimonial->display_name }}</cite>
                                                <p class="testimonial-meta text-muted small mb-0">{{ $testimonial->job_title }}{{ $testimonial->job_title && $testimonial->business_name ? ' · ' : '' }}{{ $testimonial->business_name }}</p>
                                            </div>
                                        </div>
                                    </header>

                                    <div class="testimonial-rating text-warning" role="img" aria-label="{{ $testimonial->rating }} out of 5 stars">
                                        {{ str_repeat('★', $testimonial->rating) }}
                                    </div>

                                    <div class="testimonial-divider" aria-hidden="true"></div>

                                    <blockquote class="testimonial-quote">{{ $testimonial->content }}</blockquote>
                                </article>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="pricing-card text-center mx-auto" style="max-width: 720px;">
                        <span class="pricing-icon mb-3" aria-hidden="true"><i class="bi bi-chat-quote"></i></span>
                        <h3 class="h4 fw-bold text-dark mb-2">Customer stories will appear here</h3>
                        <p class="text-muted mb-0">Approved customer testimonials will be published here as businesses share their {{ $platform->product_name }} experience.</p>
                    </div>
                @endif
            </div>
        </section>

        <section class="section-space section-surface" id="how-it-works">
            <div class="container">
                <div class="row justify-content-between align-items-end g-4 mb-5">
                    <div class="col-lg-7">
                        <p class="eyebrow">A simpler daily workflow</p>
                        <h2 class="section-heading mb-3">Get started in four simple steps</h2>
                    </div>
                    <div class="col-lg-4">
                        <p class="section-lead mb-0">Set up your workspace once, then keep every invoice and payment moving in the same direction.</p>
                    </div>
                </div>

                <ol class="process-list">
                    <li class="process-item">
                        <span class="process-number" aria-hidden="true"></span>
                        <div><h3>Create Workspace</h3><p>Set up a workspace for your business and keep its records together.</p></div>
                    </li>
                    <li class="process-item">
                        <span class="process-number" aria-hidden="true"></span>
                        <div><h3>Add Customers</h3><p>Build a clear customer list so invoices and payment history stay connected.</p></div>
                    </li>
                    <li class="process-item">
                        <span class="process-number" aria-hidden="true"></span>
                        <div><h3>Send Invoices</h3><p>Create professional invoices with the details your customers need to pay.</p></div>
                    </li>
                    <li class="process-item">
                        <span class="process-number" aria-hidden="true"></span>
                        <div><h3>Track Payments</h3><p>Record payments, follow overdue balances, and keep your next action visible.</p></div>
                    </li>
                </ol>
            </div>
        </section>

        <section class="section-space" id="benefits">
            <div class="container">
                <div class="benefit-panel">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-6">
                            <p class="eyebrow">More control, less chasing</p>
                            <h2 class="mb-3">Make every payment follow-up easier to act on.</h2>
                            <p class="mb-0">{{ $platform->product_name }} gives your team a shared place to understand what has been invoiced, what has been paid, and what needs attention next.</p>
                            <ul class="benefit-list">
                                <li><i class="bi bi-check2-circle" aria-hidden="true"></i><span>Spend less time searching for payment status.</span></li>
                                <li><i class="bi bi-check2-circle" aria-hidden="true"></i><span>Make customer follow-up part of a repeatable process.</span></li>
                                <li><i class="bi bi-check2-circle" aria-hidden="true"></i><span>Keep invoices, customers, and reports in workspace context.</span></li>
                            </ul>
                        </div>
                        <div class="col-lg-5 offset-lg-1">
                            <div class="benefit-dashboard" role="region" aria-label="Sample cash flow summary">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <strong>Cash flow focus</strong>
                                    <i class="bi bi-bar-chart-line text-primary" aria-hidden="true"></i>
                                </div>
                                <div class="mini-row"><span class="mini-label">Payments received</span><span class="mini-value text-success">{{ $homepageCurrencySymbol }}845,200</span></div>
                                <div class="mini-row"><span class="mini-label">Open customer balances</span><span class="mini-value">{{ $homepageCurrencySymbol }}486,300</span></div>
                                <div class="mini-row"><span class="mini-label">Invoices needing follow-up</span><span class="mini-value text-danger">18</span></div>
                                <div class="mt-3 small text-muted"><i class="bi bi-info-circle me-1" aria-hidden="true"></i> Sample figures for illustration only.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-space section-surface" id="who-its-for">
            <div class="container">
                <div class="text-center mx-auto mb-5" style="max-width: 700px;">
                    <p class="eyebrow">Made to fit real workflows</p>
                    <h2 class="section-heading mb-3">A clearer way to run the money side of your business</h2>
                    <p class="section-lead mx-auto mb-0">{{ $platform->product_name }} is for teams that invoice customers, receive payments, and need a reliable view of what happens next.</p>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <article class="audience-card"><span class="feature-icon"><i class="bi bi-shop" aria-hidden="true"></i></span><h3>Small businesses</h3><p>Keep day-to-day invoicing and customer balances simple as your business grows.</p></article>
                    </div>
                    <div class="col-md-4">
                        <article class="audience-card"><span class="feature-icon"><i class="bi bi-briefcase" aria-hidden="true"></i></span><h3>Service teams</h3><p>Send clear invoices, record payment progress, and follow up without losing context.</p></article>
                    </div>
                    <div class="col-md-4">
                        <article class="audience-card"><span class="feature-icon"><i class="bi bi-people" aria-hidden="true"></i></span><h3>Growing finance teams</h3><p>Work together in shared workspaces with roles, reports, and consistent processes.</p></article>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-space" id="pricing">
            <div class="container">
                <div class="pricing-card">
                    <div class="row align-items-center g-4">
                        <div class="col-auto"><span class="pricing-icon" aria-hidden="true"><i class="bi bi-arrow-right-circle"></i></span></div>
                        <div class="col-lg">
                            <p class="eyebrow mb-2">Pricing</p>
                            <h2 class="h3 fw-bold text-dark mb-2">Start with a workspace built around your workflow.</h2>
                            <p class="text-muted mb-0">Create an account to explore {{ $platform->product_name }} and set up the right invoicing and collections workflow for your business.</p>
                        </div>
                        <div class="col-lg-auto">
                            @guest
                                <a href="{{ route('register') }}" class="btn btn-primary px-4">Get Started Free</a>
                            @else
                                <a href="{{ route('dashboard') }}" class="btn btn-primary px-4">Open Dashboard</a>
                            @endguest
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-space section-surface" id="faqs">
            <div class="container">
                <div class="row justify-content-between g-5">
                    <div class="col-lg-4">
                        <p class="eyebrow">Questions, answered</p>
                        <h2 class="section-heading mb-3">Frequently asked questions</h2>
                        <p class="section-lead mb-0">A quick overview of how {{ $platform->product_name }} fits into your invoicing and payment workflow.</p>
                    </div>
                    <div class="col-lg-7">
                        <div class="accordion faq-wrap" id="faqAccordion">
                            <div class="accordion-item">
                                <h3 class="accordion-header" id="faq-heading-one"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq-one" aria-expanded="true" aria-controls="faq-one">What can I manage in {{ $platform->product_name }}?</button></h3>
                                <div id="faq-one" class="accordion-collapse collapse show" aria-labelledby="faq-heading-one" data-bs-parent="#faqAccordion"><div class="accordion-body">{{ $platform->product_name }} brings customers, invoices, payments, outstanding balances, reminder workflows, and workspace-scoped reports together in one place.</div></div>
                            </div>
                            <div class="accordion-item">
                                <h3 class="accordion-header" id="faq-heading-two"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq-two" aria-expanded="false" aria-controls="faq-two">Can I manage more than one business?</button></h3>
                                <div id="faq-two" class="accordion-collapse collapse" aria-labelledby="faq-heading-two" data-bs-parent="#faqAccordion"><div class="accordion-body">Yes. {{ $platform->product_name }} organizes work by workspace, so you can switch between active workspaces while keeping each business’s records separate.</div></div>
                            </div>
                            <div class="accordion-item">
                                <h3 class="accordion-header" id="faq-heading-three"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq-three" aria-expanded="false" aria-controls="faq-three">Does {{ $platform->product_name }} support reminders?</button></h3>
                                <div id="faq-three" class="accordion-collapse collapse" aria-labelledby="faq-heading-three" data-bs-parent="#faqAccordion"><div class="accordion-body">{{ $platform->product_name }} supports reminder schedules and reusable email templates to help teams follow up on invoices before and after their due dates.</div></div>
                            </div>
                            <div class="accordion-item">
                                <h3 class="accordion-header" id="faq-heading-four"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq-four" aria-expanded="false" aria-controls="faq-four">What about WhatsApp and SMS reminders?</button></h3>
                                <div id="faq-four" class="accordion-collapse collapse" aria-labelledby="faq-heading-four" data-bs-parent="#faqAccordion"><div class="accordion-body">WhatsApp and SMS workflows are on the {{ $platform->product_name }} roadmap. The current reminder workflow provides a clear foundation for consistent customer follow-up.</div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="cta-section" id="contact">
            <div class="container text-center">
                <p class="eyebrow">Ready when you are</p>
                <h2 class="section-heading mx-auto mb-3">Bring your invoices and payment follow-up into focus.</h2>
                <p class="section-lead mx-auto mb-4">Start with a workspace that helps your business know what was sent, what was paid, and what needs attention.</p>
                <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                    @guest
                        <a href="{{ route('register') }}" class="btn btn-primary btn-lg px-4">Get Started Free</a>
                        <a href="{{ route('login') }}" class="btn btn-outline-primary btn-lg px-4">Sign In</a>
                    @else
                        <a href="{{ route('dashboard') }}" class="btn btn-primary btn-lg px-4">Open Dashboard</a>
                    @endguest
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <a class="brand-link align-items-center d-inline-flex fw-bold text-decoration-none" href="{{ route('home') }}" aria-label="{{ $platform->product_name }} home">
                    @if ($platformSettings['logoUrl'])<img src="{{ $platformSettings['logoUrl'] }}" alt="{{ $platform->product_name }}" style="max-width:132px;height:36px;object-fit:contain">@else<span class="brand-mark" aria-hidden="true"><i class="bi bi-receipt-cutoff"></i></span>@endif
                    <span>{{ $platform->product_name }}</span>
                </a>
                <nav class="d-flex flex-wrap gap-3" aria-label="Footer navigation">
                    <a class="footer-link" href="#features">Features</a>
                    <a class="footer-link" href="#how-it-works">How It Works</a>
                    <a class="footer-link" href="#testimonials">Testimonials</a>
                    <a class="footer-link" href="#faqs">FAQs</a>
                    <a class="footer-link" href="#contact">Contact</a>
                </nav>
                @if (collect($platform->social_links ?? [])->filter()->isNotEmpty())
                    <nav class="d-flex gap-2" aria-label="Social links">
                        @foreach (($platform->social_links ?? []) as $social => $url)
                            @if ($url)
                                <a class="footer-link" href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ ucfirst($social) }}</a>
                            @endif
                        @endforeach
                    </nav>
                @endif
                <span class="copyright">{{ $platform->footer_copyright ?: 'Copyright '.now()->year.' '.$platform->product_name.'. All rights reserved.' }}</span>
            </div>
        </div>
    </footer>
@endsection
