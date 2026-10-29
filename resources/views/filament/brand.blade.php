{{-- The wordmark, not the app name as plain text. Inline SVG so it inherits
     currentColor and stays crisp on any background. «مسار» in the Arabic
     panel, the Latin MASAR in the other five, so a screen reader in an
     English panel does not announce the brand in Arabic. --}}
@php($arabic = app()->getLocale() === 'ar')
<span class="masar-wordmark" dir="{{ $arabic ? 'rtl' : 'ltr' }}" aria-label="{{ $arabic ? 'مسار' : 'MASAR' }}">
    <svg viewBox="0 0 120 32" role="img" aria-hidden="true" focusable="false" height="28">
        <title>{{ $arabic ? 'مسار' : 'MASAR' }}</title>
        <text x="{{ $arabic ? 60 : 66 }}" y="23" text-anchor="middle"
              font-family="'Readex Pro', system-ui, sans-serif"
              font-size="{{ $arabic ? 22 : 19 }}" font-weight="600" letter-spacing="{{ $arabic ? 0 : 1 }}" fill="currentColor">{{ $arabic ? 'مسار' : 'MASAR' }}</text>
        <circle cx="8" cy="16" r="4" fill="#7FB69A" />
    </svg>
</span>
