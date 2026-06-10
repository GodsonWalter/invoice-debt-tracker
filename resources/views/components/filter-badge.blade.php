@props(['field', 'operator', 'value'])

@php
    $operatorLabels = [
        '=' => 'is',
        '>' => 'greater than',
        '<' => 'less than',
        '>=' => 'at least',
        '<=' => 'at most',
    ];
    
    $label = $operatorLabels[$operator] ?? $operator;
@endphp

<span class="badge bg-light text-dark border border-secondary me-2 mb-2">
    <strong>{{ $field }}</strong>
    <span class="text-muted">{{ $label }}</span>
    <strong>{{ $value }}</strong>
</span>
