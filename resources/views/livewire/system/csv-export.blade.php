<div class="space-y-6">
    <x-form-section submit="">
        <x-slot name="title">
            {{ __('CSV Export') }}
        </x-slot>

        <x-slot name="description">
            Export call log data as CSV files for reporting and analysis.
        </x-slot>

        <x-slot name="form">
            <div class="col-span-6 space-y-4">
                <div>
                    <h4 class="text-sm font-semibold text-gray-700">System Toggle</h4>
                    <p class="text-sm text-gray-500 mt-1">
                        The system-level feature toggle for CSV Export is managed under <strong>Enabled Utilities</strong>. When disabled at the system level, no teams can access this utility.
                    </p>
                </div>

                <hr class="border-gray-200" />

                <div>
                    <h4 class="text-sm font-semibold text-gray-700">Team Access</h4>
                    <p class="text-sm text-gray-500 mt-1">
                        Team-level access to CSV Export is managed per-team in the team settings. Administrators can enable or disable this utility for individual teams as needed.
                    </p>
                </div>

                <hr class="border-gray-200" />

                <div>
                    <h4 class="text-sm font-semibold text-gray-700">Account & Billing Restrictions</h4>
                    <p class="text-sm text-gray-500 mt-1">
                        Each team's allowed account numbers and billing codes are automatically enforced during export. Users will only receive data for the clients their team is authorized to access. These restrictions are configured per-team and cannot be overridden through the export interface.
                    </p>
                </div>

                <hr class="border-gray-200" />

                <div>
                    <h4 class="text-sm font-semibold text-gray-700">Export Details</h4>
                    <p class="text-sm text-gray-500 mt-1">
                        Exports include 29 columns of call data: call identifiers, client information, caller details, call type, agent information, duration, and attribute flags. Timestamps are formatted using the configured switch data timezone. All filters from the Analytics Call Log page are available.
                    </p>
                </div>
            </div>
        </x-slot>
    </x-form-section>
</div>
