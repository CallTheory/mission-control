<button {{ $attributes->merge(
    ['type' => 'submit',
    'class' =>
    'cursor-pointer transform transition duration-700 shadow inline-flex items-center px-4 py-2 bg-primary
    border border-transparent rounded-md font-semibold text-xs text-primary-fg hover:bg-primary-hover
    uppercase tracking-widest active:bg-primary-hover focus:outline-hidden
    focus:ring focus:ring-primary/40 disabled:opacity-25 transition'
    ]) }}>
    {{ $slot }}
</button>
