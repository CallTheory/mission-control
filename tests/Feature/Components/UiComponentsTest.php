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

    /*
     * The x-table set, x-status-badge, x-filter-select, x-search-input and x-card were
     * removed once every listing became a Filament table; their behaviour is covered by
     * the table tests on the components themselves. x-flash went with them once every
     * message became a Filament notification.
     */

    public function test_badge_renders_with_its_semantic_colour(): void
    {
        $badge = $this->render('<x-badge color="green">Live</x-badge>');
        $this->assertStringContainsString('Live', $badge);
        $this->assertStringContainsString('bg-success-soft', $badge);

        $danger = $this->render('<x-badge color="red">Failed</x-badge>');
        $this->assertStringContainsString('bg-danger-soft', $danger);
    }

    public function test_toggle_and_page_header(): void
    {
        $toggle = $this->render('<x-toggle wire-model="enabled" label="Enabled" help="on/off" />');
        $this->assertStringContainsString('wire:model="enabled"', $toggle);
        $this->assertStringContainsString('Enabled', $toggle);

        $header = $this->render('<x-page-header title="Hosts"><x-slot name="actions">BTN</x-slot></x-page-header>');
        $this->assertStringContainsString('Hosts', $header);
        $this->assertStringContainsString('BTN', $header);
    }
}
