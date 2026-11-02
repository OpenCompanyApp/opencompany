<?php

namespace Tests\Feature;

use App\Agents\Tools\ToolRegistry;
use App\Models\User;
use App\Services\Integrations\IntegrationRuntime;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Contracts\Tool as LaravelTool;
use Laravel\Ai\Tools\Request;
use Mockery;
use OpenCompany\IntegrationCore\Contracts\Tool as IntegrationTool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use Tests\TestCase;

/**
 * Regression coverage for values crossing from PHP integration tools to mruby.
 *
 * Fake tools keep this at the app-owned result boundary: no credentials,
 * provider transport, or external integration is invoked by these tests.
 */
class MrubyIntegrationValueTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->agent()->create();
    }

    public function test_integration_results_preserve_i64_scalars_and_empty_container_shapes(): void
    {
        // Protect IDs, booleans, null, [] and {} from lossy PHP normalization.
        $numericObject = new \stdClass;
        $numericObject->{'0'} = 'zero';
        $numericObject->{'01'} = 'leading-zero';

        $result = $this->callIntegrationTool([
            'largest_id' => 9_007_199_254_740_993,
            'smallest_id' => -9_007_199_254_740_993,
            'enabled' => false,
            'nothing' => null,
            'items' => [],
            'empty_object' => new \stdClass,
            'numeric_object' => $numericObject,
        ]);

        self::assertSame(9_007_199_254_740_993, $result['largest_id']);
        self::assertSame(-9_007_199_254_740_993, $result['smallest_id']);
        self::assertFalse($result['enabled']);
        self::assertNull($result['nothing']);
        self::assertSame([], $result['items']);
        self::assertInstanceOf(\stdClass::class, $result['empty_object']);
        self::assertInstanceOf(\stdClass::class, $result['numeric_object']);
        self::assertSame('zero', $result['numeric_object']->{'0'});
        self::assertSame('leading-zero', $result['numeric_object']->{'01'});
    }

    public function test_structured_json_preserves_empty_and_numeric_key_objects_without_breaking_associative_consumers(): void
    {
        // Existing callers index normal JSON objects as arrays; only ambiguous object shapes stay stdClass.
        $result = $this->callLaravelTool("Provider: fake\n\nStructured data:\n".json_encode([
            'provider' => 'fake',
            'largest_id' => 9_007_199_254_740_993,
            'enabled' => false,
            'nothing' => null,
            'items' => [],
            'empty_object' => (object) [],
            'numeric_object' => (object) ['0' => 'zero'],
        ], JSON_THROW_ON_ERROR));

        self::assertSame('fake', $result['provider']);
        self::assertSame(9_007_199_254_740_993, $result['largest_id']);
        self::assertFalse($result['enabled']);
        self::assertNull($result['nothing']);
        self::assertSame([], $result['items']);
        self::assertInstanceOf(\stdClass::class, $result['empty_object']);
        self::assertInstanceOf(\stdClass::class, $result['numeric_object']);
        self::assertSame('zero', $result['numeric_object']->{'0'});
    }

    public function test_json_scalar_false_and_null_are_not_mistaken_for_unstructured_text(): void
    {
        // Text-tool JSON scalars are valid data, not the old null-decoding sentinel.
        self::assertFalse($this->callLaravelTool('false'));
        self::assertNull($this->callLaravelTool('null'));
        self::assertSame([], $this->callLaravelTool('[]'));
        self::assertInstanceOf(\stdClass::class, $this->callLaravelTool('{}'));
    }

    public function test_runtime_rejects_nonfinite_unsupported_cyclic_and_deep_values_without_stringifying_them(): void
    {
        // Regression for boundary safety: unsupported host values must never become arbitrary guest strings.
        $resource = fopen('php://temp', 'r');
        try {
            foreach ([INF, $resource, $this->cyclicObject(), $this->deepValue()] as $value) {
                try {
                    $this->callIntegrationTool($value);
                    self::fail('The invalid value should be rejected before crossing the mruby boundary.');
                } catch (\RuntimeException $exception) {
                    self::assertStringContainsString('Integration result', $exception->getMessage());
                }
            }
        } finally {
            fclose($resource);
        }
    }

    private function callIntegrationTool(mixed $data): mixed
    {
        $tool = new class($data) implements IntegrationTool
        {
            public function __construct(private mixed $data) {}

            public function name(): string
            {
                return 'fake_value';
            }

            public function description(): string
            {
                return 'Returns fake values for the mruby boundary test.';
            }

            public function parameters(): array
            {
                return [];
            }

            public function execute(array $args): ToolResult
            {
                return ToolResult::success($this->data);
            }
        };

        return $this->runtimeFor($tool)->call($this->agent, 'fake_value', []);
    }

    private function callLaravelTool(string $response): mixed
    {
        $tool = new class($response) implements LaravelTool
        {
            public function __construct(private string $response) {}

            public function description(): string
            {
                return 'Returns fake JSON text for the mruby boundary test.';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            public function handle(Request $request): string
            {
                return $this->response;
            }
        };

        return $this->runtimeFor($tool)->call($this->agent, 'fake_value', []);
    }

    private function runtimeFor(IntegrationTool|LaravelTool $tool): IntegrationRuntime
    {
        $registry = Mockery::mock(ToolRegistry::class);
        $registry->shouldReceive('resolveScriptToolForDispatch')
            ->once()
            ->with('fake_value', Mockery::type(User::class), null)
            ->andReturn(['decision' => 'allow', 'reason' => 'Allowed', 'tool' => $tool]);

        return new IntegrationRuntime($registry);
    }

    private function cyclicObject(): \stdClass
    {
        $value = new \stdClass;
        $value->self = $value;

        return $value;
    }

    private function deepValue(): array
    {
        $value = ['leaf' => true];
        for ($depth = 0; $depth <= 64; $depth++) {
            $value = ['next' => $value];
        }

        return $value;
    }
}
