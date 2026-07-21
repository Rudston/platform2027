{{-- Shared edit (pencil) icon. Use next to any "Edit" affordance for a
     consistent convention. Size via a class attribute, e.g.
     <x-icons.edit class="h-4 w-4" />. --}}
<svg {{ $attributes->merge(['class' => 'h-4 w-4']) }}
     xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
     stroke-width="1.5" stroke="currentColor" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
</svg>
