<button {{ $attributes->merge(
    ['type' => 'button',
    'class' =>
    'cursor-pointer transform transition duration-700 shadow inline-flex items-center px-4 py-2 bg-surface-2
    border border-border rounded-md font-semibold text-xs text-surface-fg
    uppercase tracking-widest hover:bg-border active:bg-border focus:outline-hidden
    focus:ring focus:ring-primary/30 disabled:opacity-25 transition'
    ]) }}>
    {{ $slot }}
</button>
