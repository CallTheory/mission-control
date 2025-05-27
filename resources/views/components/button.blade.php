<button {{ $attributes->merge(
    ['type' => 'submit',
    'class' =>
    'cursor-pointer transform transition duration-700 shadow inline-flex items-center px-4 py-2 bg-gray-700
    border border-transparent  rounded-md font-semibold text-xs text-gray-100 hover:text-white
    uppercase tracking-widest hover:bg-gray-900   active:bg-gray-900 focus:outline-hidden
    focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition'
    ]) }}>
    {{ $slot }}
</button>
