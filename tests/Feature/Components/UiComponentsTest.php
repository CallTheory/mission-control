<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class UiComponentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Components that render <x-input-error> need an $errors bag bound.
        view()->share('errors', new ViewErrorBag);
    }

    private function render(string $template, array $data = []): string
    {
        return Blade::render($template, $data);
    }

    public function test_form_field_renders_label_input_and_help(): void
    {
        $html = $this->render(
            '<x-form-field for="foo" label="Foo Field" error-for="state.foo" help="Hint text" wire:model="state.foo" placeholder="pp" />'
        );

        $this->assertStringContainsString('Foo Field', $html);
        $this->assertStringContainsString('id="foo"', $html);
        $this->assertStringContainsString('wire:model="state.foo"', $html);
        $this->assertStringContainsString('placeholder="pp"', $html);
        $this->assertStringContainsString('Hint text', $html);
    }

    public function test_form_field_slot_overrides_control(): void
    {
        $html = $this->render(
            '<x-form-field for="s" label="Pick"><select id="s"><option>A</option></select></x-form-field>'
        );

        $this->assertStringContainsString('<select id="s"', $html);
        $this->assertStringContainsString('Pick', $html);
    }

    public function test_table_set_renders(): void
    {
        $html = $this->render(<<<'BLADE'
            <x-table>
                <x-slot name="head">
                    <x-table.heading>Name</x-table.heading>
                </x-slot>
                <x-table.row>
                    <x-table.cell>Alice</x-table.cell>
                    <x-table.cell muted>muted</x-table.cell>
                </x-table.row>
                <x-table.empty :colspan="2">Nothing here.</x-table.empty>
                <x-slot name="footer">PAGER</x-slot>
            </x-table>
        BLADE);

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('Name', $html);
        $this->assertStringContainsString('Alice', $html);
        $this->assertStringContainsString('colspan="2"', $html);
        $this->assertStringContainsString('PAGER', $html);
    }

    public function test_badge_and_status_badge(): void
    {
        $badge = $this->render('<x-badge color="green">Live</x-badge>');
        $this->assertStringContainsString('Live', $badge);
        $this->assertStringContainsString('bg-green-100', $badge);

        $delivered = $this->render('<x-status-badge status="delivered" />');
        $this->assertStringContainsString('bg-green-100', $delivered);
        $this->assertStringContainsString('Delivered', $delivered);

        $failed = $this->render('<x-status-badge status="failed" />');
        $this->assertStringContainsString('bg-red-100', $failed);
    }

    public function test_flash_renders_session_messages(): void
    {
        session()->flash('message', 'Saved OK');
        session()->flash('error', 'Bad thing');

        $html = $this->render('<x-flash />');

        $this->assertStringContainsString('Saved OK', $html);
        $this->assertStringContainsString('Bad thing', $html);
    }

    public function test_filter_select_and_search_input(): void
    {
        $select = $this->render(
            '<x-filter-select for="status" label="Status" wire-model="filterStatus" :options="$opts" />',
            ['opts' => [['id' => 'a', 'name' => 'Alpha'], ['id' => 'b', 'name' => 'Beta']]]
        );
        $this->assertStringContainsString('wire:model.live="filterStatus"', $select);
        $this->assertStringContainsString('Alpha', $select);
        $this->assertStringContainsString('value="a"', $select);

        $search = $this->render('<x-search-input wire-model="search" />');
        $this->assertStringContainsString('wire:model.live="search"', $search);
    }

    public function test_toggle_card_and_page_header(): void
    {
        $toggle = $this->render('<x-toggle wire-model="enabled" label="Enabled" help="on/off" />');
        $this->assertStringContainsString('wire:model="enabled"', $toggle);
        $this->assertStringContainsString('Enabled', $toggle);

        $card = $this->render('<x-card title="My Card">Body</x-card>');
        $this->assertStringContainsString('My Card', $card);
        $this->assertStringContainsString('Body', $card);

        $header = $this->render('<x-page-header title="Hosts"><x-slot name="actions">BTN</x-slot></x-page-header>');
        $this->assertStringContainsString('Hosts', $header);
        $this->assertStringContainsString('BTN', $header);
    }
}
