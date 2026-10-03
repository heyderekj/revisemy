<?php

namespace App\Support;

use App\Mcp\Servers\ReviseMyServer;
use App\Models\Review;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Ui\Enums\Visibility;
use ReflectionClass;

/**
 * What the MCP server offers an agent, read from the server itself: the tools
 * and prompts it registers, by the names and descriptions on their attributes.
 * llms.txt, the server card, the markdown pages and the structured data all
 * read from here, so the public list can't drift from what agents can call.
 */
final class McpCatalog
{
    /**
     * Tools an agent can call. Leaves out the ones only the inline review's
     * human uses, and the paid ones while paid pricing is off.
     *
     * @return list<array{name: string, description: string}>
     */
    public static function tools(): array
    {
        $server = new ReflectionClass(ReviseMyServer::class);
        $paidOnly = $server->getConstant('PAID_ONLY_TOOLS') ?: [];
        $pricing = (bool) config('billing.pricing_enabled');

        return collect($server->getDefaultProperties()['tools'] ?? [])
            ->reject(fn (string $tool) => ! $pricing && in_array($tool, $paidOnly, true))
            ->reject(fn (string $tool) => self::humanOnly($tool))
            ->map(fn (string $tool) => self::describe($tool))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function toolNames(): array
    {
        return array_column(self::tools(), 'name');
    }

    /**
     * @return list<array{name: string, description: string}>
     */
    public static function prompts(): array
    {
        $server = new ReflectionClass(ReviseMyServer::class);

        return collect($server->getDefaultProperties()['prompts'] ?? [])
            ->map(fn (string $prompt) => self::describe($prompt))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function nextActions(): array
    {
        return Review::NEXT_ACTIONS;
    }

    public static function endpoint(): string
    {
        return rtrim((string) config('app.url'), '/').config('seo.mcp_path');
    }

    /**
     * @param  class-string  $class
     * @return array{name: string, description: string}
     */
    protected static function describe(string $class): array
    {
        $reflection = new ReflectionClass($class);

        return [
            'name' => self::attribute($reflection, Name::class)?->value ?? $reflection->getShortName(),
            'description' => self::attribute($reflection, Description::class)?->value ?? '',
        ];
    }

    /** Rendered by the inline review for the human, and hidden from the model. */
    protected static function humanOnly(string $class): bool
    {
        $renders = self::attribute(new ReflectionClass($class), RendersApp::class);

        return $renders !== null && ! in_array(Visibility::Model, $renders->visibility, true);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $attribute
     * @return T|null
     */
    protected static function attribute(ReflectionClass $class, string $attribute): ?object
    {
        return ($class->getAttributes($attribute)[0] ?? null)?->newInstance();
    }
}
