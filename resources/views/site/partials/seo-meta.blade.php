@php
    $metaTitle = $title ? $title.' · '.config('app.name') : config('app.name');
    $metaDescription = $description ?? config('site.tagline');
@endphp
<meta name="description" content="{{ $metaDescription }}">
@if ($indexable)
    <link rel="canonical" href="{{ url()->current() }}">
@else
    <meta name="robots" content="noindex">
@endif
<meta name="theme-color" content="#020617">

<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:title" content="{{ $metaTitle }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:type" content="website">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ asset('og-image.jpg') }}">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="675">
<meta property="og:image:alt" content="Your angiograms, anywhere, anytime, without CDs: a doctor and a patient reviewing a coronary angiogram on a laptop.">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $metaTitle }}">
<meta name="twitter:description" content="{{ $metaDescription }}">
<meta name="twitter:image" content="{{ asset('og-image.jpg') }}">
<meta name="twitter:image:alt" content="Your angiograms, anywhere, anytime, without CDs: a doctor and a patient reviewing a coronary angiogram on a laptop.">
