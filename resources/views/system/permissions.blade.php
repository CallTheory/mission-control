@php
    $enabled = '<svg class="w-6 h-6 inline-flex text-green-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>';
    $disabled = '<svg class="w-6 h-6 inline-flex text-red-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>';

@endphp
<x-app-layout>
    <x-slot name="header">

        <h2 class="inline font-semibold text-xl leading-tight ">
            <a href="/system">System Settings</a> <livewire:system.dropdown-navigation />
        </h2>

    </x-slot>

    <div class="p-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class=" sm:rounded-lg  flex">

                <div class=" w-full p-12 border border-gray-300 border-double bg-white  shadow   mx-auto rounded-lg">

                    <div class="block mb-4">
                        Permissions are based on the user-role set for the application login. These roles and associated permissions are documented below for reference.
                        To adjust permissions, please change the role of the user to match the corresponding permission sets.
                    </div>

                    <x-alert-info title="Planned Improvement" description="Ability to customize permission options for each role." />

                    <h3 class="text-2xl mt-8">Team Permissions</h3>
                    <div class="shadow overflow-y-auto border border-gray-300  sm:rounded-lg block mt-4">
                        <table class="min-w-full divide-y divide-gray-300  text-center">
                            <thead class="">
                            <tr class="sticky top-0">
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Role</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">View Team</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Create Team</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Update Team</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Add Team Member</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Update Team Member</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Remove Team Member</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white  divide-y divide-gray-300 ">

                            <tr class="group  transform transition duration-700 ease-in-out text-center">
                                <td class="mx-auto px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    Administrator
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>

                            </tr>

                            <tr class="group  transform transition duration-700 ease-in-out text-center">
                                <td class="mx-auto px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    Technical
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>

                            </tr>

                            <tr class="group  transform transition duration-700 ease-in-out text-center">
                                <td class="mx-auto px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    Manager
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>

                            </tr>

                            <tr class="group  transform transition duration-700 ease-in-out text-center">
                                <td class="mx-auto px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    Supervisor
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                            </tr>


                            <tr class="group  transform transition duration-700 ease-in-out text-center">
                                <td class="mx-auto px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    Dispatcher
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>

                            </tr>

                            <tr class="group  transform transition duration-700 ease-in-out text-center">
                                <td class="mx-auto px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    Agent
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>

                            </tr>


                            </tbody>
                        </table>
                    </div>

                    <h3 class="text-2xl mt-8">Feature Permissions</h3>
                    <div class="shadow overflow-y-auto border border-gray-300  sm:rounded-lg block mt-4">
                        <table class="min-w-full divide-y divide-gray-300  text-center">
                            <thead class="">
                            <tr class="sticky top-0">
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Role</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Scope</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Call Lookup</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Card Processing</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Inbound Email</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Board Check</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Cloud Faxing</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium text-gray-500 0 uppercase tracking-wider whitespace-nowrap">Database Health</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white  divide-y divide-gray-300 ">

                            <tr class="group  transform transition duration-700 ease-in-out text-center">
                                <td class="mx-auto px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    Administrator
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    System
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                            </tr>

                            <tr class="group  transform transition duration-700 ease-in-out text-center">
                                <td class="mx-auto px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    Technical
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    System
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                            </tr>

                            <tr class="group  transform transition duration-700 ease-in-out text-center">
                                <td class="mx-auto px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    Manager
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    Team
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                            </tr>

                            <tr class="group  transform transition duration-700 ease-in-out text-center">
                                <td class="mx-auto px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    Supervisor
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    Team
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                            </tr>


                            <tr class="group  transform transition duration-700 ease-in-out text-center">
                                <td class="mx-auto px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    Dispatcher
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    Team
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                            </tr>

                            <tr class="group  transform transition duration-700 ease-in-out text-center">
                                <td class="mx-auto px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    Agent
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    Self
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $enabled !!}
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                                <td class="tpx-6 py-4 whitespace-nowrap text-sm text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-900   transform transition duration-700 ease-in-out">
                                    {!! $disabled !!}
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                </div>

            </div>
        </div>
    </div>



</x-app-layout>
