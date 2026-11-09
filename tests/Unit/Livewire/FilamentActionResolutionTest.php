<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire;

use Filament\Actions\Action;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\Finder\Finder;

/**
 * A Filament action that renders but cannot be clicked.
 *
 * Filament draws an action from whatever object the page hands it, but a click is a
 * new request, and Filament finds the action again by its *name*: an action made as
 * Action::make('foo') is looked up as a method called fooAction() (or foo()) on the
 * component. If that method is missing, takes arguments, or makes an action of a
 * different name, resolveAction() returns null and mountAction() unmounts in silence:
 * no exception, no log, nothing in GlitchTip. The button simply does nothing.
 *
 * That is how card processing stopped charging after the Filament conversion -- one
 * processAction(bool $production) built both buttons, named processTest and
 * processLive, and neither name had a method.
 *
 * Table and schema actions are found through the table or schema instead, so this
 * only guards the methods Filament resolves by name: every `fooAction()` method on a
 * Livewire component that returns an Action.
 */
class FilamentActionResolutionTest extends TestCase
{
    /**
     * @return array<string, array{ReflectionMethod}>
     */
    public static function actionMethods(): array
    {
        $root = dirname(__DIR__, 3);
        $methods = [];

        foreach ((new Finder)->files()->in($root.'/app/Livewire')->name('*.php') as $file) {
            $class = 'App\\Livewire\\'.str_replace(
                ['/', '.php'],
                ['\\', ''],
                $file->getRelativePathname(),
            );

            if (! class_exists($class) && ! trait_exists($class)) {
                continue;
            }

            foreach ((new ReflectionClass($class))->getMethods() as $method) {
                // Only methods written here: the framework's own mountAction(),
                // getAction() and so on also end in "Action".
                if ($method->getFileName() !== $file->getRealPath()) {
                    continue;
                }

                if (! str_ends_with($method->getName(), 'Action') || ! self::returnsAction($method)) {
                    continue;
                }

                $methods[$class.'::'.$method->getName()] = [$method];
            }
        }

        return $methods;
    }

    private static function returnsAction(ReflectionMethod $method): bool
    {
        $type = $method->getReturnType();

        return $type instanceof ReflectionNamedType && is_a($type->getName(), Action::class, allow_string: true);
    }

    public function test_there_are_action_methods_to_check(): void
    {
        // A scan that finds nothing would pass every assertion below.
        $this->assertGreaterThan(10, count(self::actionMethods()));
    }

    #[DataProvider('actionMethods')]
    public function test_an_action_method_can_be_called_without_arguments(ReflectionMethod $method): void
    {
        // Filament calls it with none. A required parameter means the action cannot
        // be resolved from a click -- whatever the page rendered.
        $this->assertSame(
            0,
            $method->getNumberOfRequiredParameters(),
            "{$method->class}::{$method->getName()}() takes required arguments, so a click on its action resolves to nothing. Give each variant its own no-argument method.",
        );
    }

    #[DataProvider('actionMethods')]
    public function test_an_action_method_makes_the_action_it_is_named_for(ReflectionMethod $method): void
    {
        $source = implode('', array_slice(
            file($method->getFileName()),
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1,
        ));

        $expected = Str::beforeLast($method->getName(), 'Action');

        // The method either builds the action itself, or returns another method's.
        if (preg_match('/Action::make\(\s*([^)]*?)\s*\)/', $source, $make)) {
            $this->assertSame(
                "'{$expected}'",
                $make[1],
                "{$method->class}::{$method->getName()}() makes Action::make({$make[1]}). A click looks the action up by name, as {$expected}Action(), so the two must agree.",
            );

            return;
        }

        $this->assertMatchesRegularExpression(
            '/return\s+\$this->\w+\(/',
            $source,
            "{$method->class}::{$method->getName()}() neither makes an action nor returns one built elsewhere.",
        );
    }
}
