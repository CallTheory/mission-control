<?php

namespace App\Livewire\Utilities;

use Illuminate\View\View;
use Livewire\Component;

class ApiGateway extends Component
{
    public array $apis = [];

    public function mount(): void
    {

        $this->apis[] = [
            'logo' => '/images/mission-control.png',
            'name' => 'Recent Caller',
            'api' => 'Mission Control',
            'description' => 'Returns previous caller data (fields entered into scripts for that number)',
            'example' => 'See documentation',
            'docs' => 'https://learn.calltheory.com/mission-control/api/agents/recent-caller/',
        ];

        $this->apis[] = [
            'logo' => '/images/mission-control.png',
            'name' => 'Kebab Case',
            'api' => 'Mission Control',
            'description' => 'Turn any string into a kebab-case string',
            'example' => '"123 Main Street" => "1-2-3 M-A-I-N S-T-R-E-E-T"',
            'docs' => 'https://learn.calltheory.com/mission-control/api/utilities/kebab-case/',
        ];

        $this->apis[] = [
            'logo' => '/images/mission-control.png',
            'name' => 'Studly Case',
            'api' => 'Mission Control',
            'description' => 'Turn any string into a StudlyCase string',
            'example' => '"Call Theory" => "CallTheory"',
            'docs' => 'https://learn.calltheory.com/mission-control/api/utilities/studly-case/',
        ];

        $this->apis[] = [
            'logo' => '/images/mission-control.png',
            'name' => 'Snake Case',
            'api' => 'Mission Control',
            'description' => 'Turn any string into a snake_case string',
            'example' => '"Call Theory" => "call_theory"',
            'docs' => 'https://learn.calltheory.com/mission-control/api/utilities/snake-case/',
        ];

        $this->apis[] = [
            'logo' => '/images/mission-control.png',
            'name' => 'Camel Case',
            'api' => 'Mission Control',
            'description' => 'Turn any string into a camelCase string',
            'example' => '"Call Theory" => "callTheory"',
            'docs' => 'https://learn.calltheory.com/mission-control/api/utilities/camel-case/',
        ];

        $this->apis[] = [
            'logo' => '/images/mission-control.png',
            'name' => 'Title Case',
            'api' => 'Mission Control',
            'description' => 'Turn any string into a Title Case string',
            'example' => '"Mission Control: a dashboard by Call Theory" => "Mission Control: A Dashboard By Call Theory"',
            'docs' => 'https://learn.calltheory.com/mission-control/api/utilities/title-case/',
        ];

        $this->apis[] = [
            'logo' => '/images/mission-control.png',
            'name' => 'APA Title Case',
            'api' => 'Mission Control',
            'description' => 'Turn any string into a APA Title Case string',
            'example' => '"Mission Control: a dashboard by Call Theory" => "Mission Control: A Dashboard by Call Theory"',
            'docs' => 'https://learn.calltheory.com/mission-control/api/utilities/apa-title-case/',
        ];

        $this->apis[] = [
            'logo' => '/images/mission-control.png',
            'name' => 'Base64 Encode',
            'api' => 'Mission Control',
            'description' => 'Base64 encode the supplied string',
            'example' => '"Call Theory" => "Q2FsbCBUaGVvcnk="',
            'docs' => 'https://learn.calltheory.com/mission-control/api/utilities/base64-encode/',
        ];

        $this->apis[] = [
            'logo' => '/images/mission-control.png',
            'name' => 'Transliterate ASCII',
            'api' => 'Mission Control',
            'description' => 'Transliterate the string into the ASCII equivalent',
            'example' => '"û" => "u"',
            'docs' => 'https://learn.calltheory.com/mission-control/api/utilities/transliterate-ascii/',
        ];

        $this->apis[] = [
            'logo' => '/images/mission-control.png',
            'name' => 'Text Between',
            'api' => 'Mission Control',
            'description' => 'Returns the portion of a string between two values',
            'example' => '(<html><title>catch me if you can</title></html>, <title>, </title>) => "catch me if you can"',
            'docs' => 'https://learn.calltheory.com/mission-control/api/utilities/text-between/',
        ];

        $this->apis[] = [
            'logo' => '/images/mission-control.png',
            'name' => 'Preg Match',
            'api' => 'Mission Control',
            'description' => 'Returns the regular expression matches from the supplied string',
            'example' => '(Call 555-1212 or 1-800-555-1212, /\(?  (\d{3})?  \)?  (?(1)  [\-\s] ) \d{3}-\d{4}/x) => ["555-1212", "1-800-555-1212"]',
            'docs' => 'https://learn.calltheory.com/mission-control/api/utilities/preg-match/',
        ];

        $this->apis[] = [
            'logo' => '/images/mission-control.png',
            'name' => 'Is JSON',
            'api' => 'Mission Control',
            'description' => 'Returns true if the supplied string is valid JSON, otherwise false.',
            'example' => '{"name":"jason"} => true',
            'docs' => 'https://learn.calltheory.com/mission-control/api/utilities/is-json/',
        ];

        $this->apis[] = [
            'logo' => '/images/mission-control.png',
            'name' => 'Is URL',
            'api' => 'Mission Control',
            'description' => 'Returns true if the supplied string is a valid URL, otherwise false.',
            'example' => 'http://example.com => true',
            'docs' => 'https://learn.calltheory.com/mission-control/api/utilities/is-url/',
        ];
    }

    public function render(): View
    {
        return view('livewire.utilities.api-gateway');
    }
}
