<?php

namespace Database\Factories;

use App\Models\InboundEmailRules;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InboundEmailRules>
 */
class InboundEmailRulesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Test rule',
            'rules' => [
                'to' => ['contains' => ['example.com']],
                'from' => ['exact_match' => ['sender@example.com']],
                'subject' => ['starts_with' => ['Test']],
                'text' => ['contains' => ['test email']],
                'attachment' => ['exact_match' => ['testfile.txt']],
            ],
        ];
    }
}
