<button {{ $attributes->merge(
    ['type' => 'button',
    'class' =>
    'cursor-pointer transform transition duration-700 shadow inline-flex items-center px-4 py-2 bg-gray-400
    border border-transparent  rounded-md font-semibold text-xs text-gray-100
    uppercase tracking-widest hover:bg-gray-500 hover:text-white  active:bg-gray-400 focus:outline-hidden
    focus:border-gray-500 focus:ring focus:ring-gray-300 disabled:opacity-25 transition'
    ]) }}>
    {{ $slot }}
</button>
