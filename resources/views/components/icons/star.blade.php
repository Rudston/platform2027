{{-- A filled star. Colour is set by the caller, so the same glyph serves both
     the chosen and unchosen states — outline vs fill would shift the row's
     visual weight as you hover across it. --}}
<svg {{ $attributes->merge(['class' => 'h-5 w-5']) }} viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
    <path d="M10 1.5l2.6 5.28 5.83.85-4.22 4.11.996 5.81L10 14.8l-5.21 2.74.996-5.81L1.564 7.63l5.83-.85L10 1.5z" />
</svg>
