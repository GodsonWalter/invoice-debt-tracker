@extends('layouts.app')

@section('page_title', 'AI Dashboard Queries')

@section('content')

    <div class="container-fluid py-2">
        {{-- Header --}}
        <div class="mb-5">
            <h2 class="fs-4 fw-bold text-dark mb-1">AI Dashboard Queries</h2>
            <p class="text-muted small mb-0">Ask questions about invoices using natural language.</p>
        </div>

        {{-- Search Card --}}
        <div class="card border-light shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-body p-4">
                <div class="d-flex gap-2">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fa-solid fa-wand-magic-sparkles text-primary"></i>
                        </span>
                        <input type="text" id="query-input" class="form-control border-start-0 ps-0 fs-6"
                            placeholder="Ask something like &quot;Which invoices are overdue by more than 30 days?&quot;" />
                    </div>
                    <button id="search-button" class="btn btn-primary btn-lg px-4" type="button">
                        <i class="fa-solid fa-arrow-right"></i>
                        <span class="d-none d-sm-inline ms-2">Ask</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Suggested Queries --}}
        <div class="mb-4">
            <p class="text-muted small fw-semibold text-uppercase mb-3">Suggested queries</p>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm suggestion-btn"
                    data-query="Show me overdue invoices">
                    Overdue invoices
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm suggestion-btn"
                    data-query="Show unpaid invoices">
                    Unpaid invoices
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm suggestion-btn"
                    data-query="Show invoices above 500000">
                    Invoices above ₦500,000
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm suggestion-btn"
                    data-query="Show invoices due this week">
                    Invoices due this week
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm suggestion-btn"
                    data-query="Show the largest unpaid invoices">
                    Largest unpaid invoices
                </button>
            </div>
        </div>

        {{-- Results Area --}}
        <div id="results-container" class="d-none">
            {{-- Loading State --}}
            <div id="loading-state" class="d-none">
                <div class="card border-light shadow-sm rounded-4">
                    <div class="card-body p-5 text-center">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted fw-semibold">Analyzing your request...</p>
                    </div>
                </div>
            </div>

            {{-- Error State --}}
            <div id="error-state" class="d-none mb-4">
                <x-ai-error-state />
            </div>

            {{-- Success Results --}}
            <div id="results-success" class="d-none">
                {{-- Original Query Card --}}
                <div class="card border-light shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h6 class="text-muted small fw-bold text-uppercase mb-2">Your Query</h6>
                        <p id="original-query" class="mb-0 fs-6 text-dark fw-semibold"></p>
                    </div>
                </div>

                {{-- AI Interpretation Card --}}
                <div class="card border-light shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h6 class="text-muted small fw-bold text-uppercase mb-3">How we understood it</h6>
                        <div id="parsed-filters" class="d-flex flex-wrap gap-2"></div>
                    </div>
                </div>

                {{-- Results Summary Card --}}
                <div class="card border-light shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted small fw-bold text-uppercase mb-2">Results</h6>
                                <h3 id="results-count" class="fs-3 fw-bold text-dark mb-0">0</h3>
                                <p class="text-muted small mb-0" id="results-label">invoices found</p>
                            </div>
                            <div>
                                <i class="fa-solid fa-chart-line text-primary fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Results Table --}}
                <div class="card border-light shadow-sm rounded-4">
                    <div id="table-container">
                        {{-- Results table will be inserted here --}}
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Searches --}}
        @if ($recentQueries->isNotEmpty())
            <div class="mt-5 pt-4 border-top">
                <h6 class="text-muted small fw-bold text-uppercase mb-3">Recent searches</h6>
                <div class="d-flex flex-column gap-2">
                    @foreach ($recentQueries as $recent)
                        <button type="button" class="btn btn-white text-start border border-light rounded-3 p-3 history-btn"
                            data-query="{{ $recent->query }}">
                            <div class="d-flex justify-content-between align-items-center w-100">
                                <div class="text-start">
                                    <p class="mb-1 small text-dark">{{ Str::limit($recent->query, 60) }}</p>
                                    <p class="mb-0 text-muted" style="font-size: 0.75rem;">
                                        {{ $recent->created_at->diffForHumans() }}</p>
                                </div>
                                <i class="fa-solid fa-arrow-up-right text-muted"></i>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="container">

        {{-- chart box --}}
        <div id="chart-box" class="card border-light shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h6 class="text-muted small fw-bold text-uppercase mb-3">AI Chart Example</h6>
                <div>
                    <textarea id="chart-box-question" class="form-control" rows="5"></textarea>
                    <button class="btn btn-primary mt-3" onclick="askPuter()">Ask Puter</button>
                    <p id="chart-box-answer">Your reply will appare here</p>
                </div>
            </div>
        </div>

        {{-- <script src="https://js.puter.com/v2/"></script> --}}
        <script>

            function askPuter() {
                // add loading state
                document.getElementById('chart-box-answer').innerText = 'Thinking...';
                // add loading spinner
                const spinner = document.createElement('div');
                spinner.classList.add('spinner-border', 'text-primary', 'spinner-border-sm', 'me-2');
                document.getElementById('chart-box-answer').prepend(spinner);
                const question = document.getElementById('chart-box-question').value;
                puter.ai.chat(question, { model: "gpt-5.4-nano" })
                    .then(response => {
                        // puter.print(response);
                        document.getElementById('chart-box-answer').innerText = response;
                    });
            }
            
        </script>

    </div>

    @push('scripts')
        <script src="{{ asset('js/ai-query.js') }}"></script>
    @endpush
@endsection