<button {{ $attributes->merge(['type' => 'button', 'class' => 'cursor-pointer inline-flex items-center justify-center px-4 py-2 bg-danger border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-danger-hover focus:outline-hidden focus:ring focus:ring-danger/40 active:bg-danger-hover disabled:opacity-25 transition']) }}>
    {{ $slot }}
</button>
