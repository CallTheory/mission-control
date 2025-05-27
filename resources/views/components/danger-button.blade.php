<button {{ $attributes->merge(['type' => 'button', 'class' => 'cursor-pointer inline-flex items-center justify-center px-4 py-2 bg-red-700 border border-transparent  rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-600 focus:outline-hidden  focus:border-red-800 focus:ring focus:ring-red-300 active:bg-red-700 disabled:opacity-25 transition']) }}>
    {{ $slot }}
</button>
