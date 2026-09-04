@php
    $reportCompany = $company ?? auth()->user()?->company;
    $logoSrc = $reportCompany?->reportLogoDataUri() ?? \App\Models\Company::defaultReportLogoDataUri();
@endphp
<header style="margin-bottom: 12px;">
    <img
        src="{{ $logoSrc }}"
        alt="{{ $reportCompany?->name ?? 'Agostini Tecnologia de Gestão' }}"
        style="display: block; width: auto; height: auto; max-width: 150px; max-height: 60px;"
    >
</header>
