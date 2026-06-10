document.addEventListener('DOMContentLoaded', function () {
    const queryInput = document.getElementById('query-input');
    const searchButton = document.getElementById('search-button');
    const resultsContainer = document.getElementById('results-container');
    const loadingState = document.getElementById('loading-state');
    const errorState = document.getElementById('error-state');
    const successState = document.getElementById('results-success');
    const tableContainer = document.getElementById('table-container');

    // Handle search button click
    searchButton.addEventListener('click', function () {
        const query = queryInput.value.trim();
        if (query) {
            submitQuery(query);
        }
    });

    // Handle Enter key in input
    queryInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            const query = queryInput.value.trim();
            if (query) {
                submitQuery(query);
            }
        }
    });

    // Handle suggestion buttons
    document.querySelectorAll('.suggestion-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const query = this.dataset.query;
            queryInput.value = query;
            submitQuery(query);
        });
    });

    // Handle history buttons
    document.querySelectorAll('.history-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const query = this.dataset.query;
            queryInput.value = query;
            submitQuery(query);
        });
    });

    // Submit query via AJAX
    function submitQuery(query) {
        // Show loading state
        resultsContainer.classList.remove('d-none');
        loadingState.classList.remove('d-none');
        errorState.classList.add('d-none');
        successState.classList.add('d-none');

        // Disable controls
        queryInput.disabled = true;
        searchButton.disabled = true;

        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        // Send request
        fetch('/dashboard/ai-query', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ query: query }),
        })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.error || 'An error occurred');
                    });
                }
                return response.json();
            })
            .then(data => {
                loadingState.classList.add('d-none');
                renderResults(data);
            })
            .catch(error => {
                loadingState.classList.add('d-none');
                errorState.classList.remove('d-none');
                updateErrorMessage(error.message);
            })
            .finally(() => {
                // Re-enable controls
                queryInput.disabled = false;
                searchButton.disabled = false;
            });
    }

    function renderResults(data) {
        if (!data.success) {
            errorState.classList.remove('d-none');
            updateErrorMessage(data.error || 'Failed to process query');
            return;
        }

        // Show success state
        successState.classList.remove('d-none');

        // Display original query
        document.getElementById('original-query').textContent = data.query;

        // Display parsed filters
        const filtersContainer = document.getElementById('parsed-filters');
        filtersContainer.innerHTML = '';

        if (data.parsed.filters && data.parsed.filters.length > 0) {
            data.parsed.filters.forEach(filter => {
                const badge = document.createElement('span');
                badge.className = 'badge bg-light text-dark border border-secondary me-2 mb-2';
                badge.innerHTML = `<strong>${filter.field}</strong> <span class="text-muted">${getOperatorLabel(filter.operator)}</span> <strong>${filter.value}</strong>`;
                filtersContainer.appendChild(badge);
            });
        } else {
            filtersContainer.innerHTML = '<p class="text-muted small">No specific filters applied</p>';
        }

        // Update results count
        const count = data.count || 0;
        document.getElementById('results-count').textContent = count;
        document.getElementById('results-label').textContent = count === 1 ? 'invoice found' : 'invoices found';

        // Render results table
        renderResultsTable(data.results, count);
    }

    function renderResultsTable(results, count) {
        tableContainer.innerHTML = '';

        if (count === 0) {
            tableContainer.appendChild(createEmptyStateElement());
            return;
        }

        // Create table
        const table = document.createElement('div');
        table.className = 'table-responsive';

        const tableElement = document.createElement('table');
        tableElement.className = 'table table-hover mb-0';

        // Table header
        const thead = document.createElement('thead');
        thead.className = 'bg-light';
        thead.innerHTML = `
            <tr>
                <th class="px-4 py-3">Invoice #</th>
                <th class="px-4 py-3">Customer</th>
                <th class="px-4 py-3">Amount</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Due Date</th>
                <th class="px-4 py-3">Days Overdue</th>
            </tr>
        `;
        tableElement.appendChild(thead);

        // Table body
        const tbody = document.createElement('tbody');
        results.forEach(invoice => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="px-4 py-3 fw-semibold">${invoice.invoice_number || 'N/A'}</td>
                <td class="px-4 py-3">${invoice.client?.name || 'N/A'}</td>
                <td class="px-4 py-3">${formatCurrency(invoice.total_amount)}</td>
                <td class="px-4 py-3">
                    <span class="badge ${getStatusBadgeClass(invoice.status)}">
                        ${invoice.status.charAt(0).toUpperCase() + invoice.status.slice(1)}
                    </span>
                </td>
                <td class="px-4 py-3">${formatDate(invoice.due_date)}</td>
                <td class="px-4 py-3">
                    ${getOverdueStatus(invoice)}
                </td>
            `;
            tbody.appendChild(row);
        });

        tableElement.appendChild(tbody);
        table.appendChild(tableElement);
        tableContainer.appendChild(table);
    }

    function createEmptyStateElement() {
        const emptyState = document.createElement('div');
        emptyState.className = 'card-body p-5 text-center';
        emptyState.innerHTML = `
            <div class="mb-3">
                <i class="fa-solid fa-inbox fs-1 text-muted"></i>
            </div>
            <h5 class="text-dark fw-semibold mb-2">No matching invoices found</h5>
            <p class="text-muted small mb-0">Try adjusting your query.</p>
        `;
        return emptyState;
    }

    function getStatusBadgeClass(status) {
        const classes = {
            'draft': 'bg-secondary',
            'sent': 'bg-info',
            'partial': 'bg-warning',
            'paid': 'bg-success',
            'overdue': 'bg-danger',
        };
        return classes[status] || 'bg-secondary';
    }

    function getOperatorLabel(operator) {
        const labels = {
            '=': 'is',
            '>': 'greater than',
            '<': 'less than',
            '>=': 'at least',
            '<=': 'at most',
        };
        return labels[operator] || operator;
    }

    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-NG', {
            style: 'currency',
            currency: 'NGN',
        }).format(amount || 0);
    }

    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-NG', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    }

    function getOverdueStatus(invoice) {
        if (invoice.status !== 'overdue') {
            return '<span class="text-muted">—</span>';
        }

        const dueDate = new Date(invoice.due_date);
        const today = new Date();
        const diffTime = Math.abs(today - dueDate);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        return `<span class="text-danger fw-semibold">${diffDays} days</span>`;
    }

    function updateErrorMessage(message) {
        const errorCard = errorState.querySelector('.alert');
        if (errorCard) {
            const messageElement = errorCard.querySelector('p');
            if (messageElement) {
                messageElement.textContent = message;
            }
        }
    }
});
