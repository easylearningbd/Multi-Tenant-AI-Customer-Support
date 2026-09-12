@props(['inverse' => false])

<div {{ $attributes->class(['inline-flex items-center gap-3']) }}>
    <span @class([
        'grid size-10 place-items-center rounded-xl shadow-sm',
        'bg-white/10 ring-1 ring-white/20' => $inverse,
        'bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-600' => ! $inverse,
    ]) aria-hidden="true">
        <svg class="size-6 text-white" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M7 17V7l10 10V7" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </span>
    <span @class([
        'text-xl font-semibold tracking-tight',
        'text-white' => $inverse,
        'text-slate-950' => ! $inverse,
    ])>NeuralDesk</span>
</div>
