<?php
declare(strict_types=1);

namespace Mercure\Update;

use Cake\View\ViewBuilder;
use InvalidArgumentException;
use Mercure\Internal\ConfigurationHelper;

/**
 * View Update
 *
 * Specialized Update class that automatically renders CakePHP views or elements
 * and uses the rendered output as the update data.
 *
 * Example usage:
 * ```
 * // Render an element
 * $update = ViewUpdate::create(
 *     topics: '/books/1',
 *     element: 'Books/item',
 *     data: ['book' => $book]
 * );
 *
 * // Render a template
 * $update = ViewUpdate::create(
 *     topics: '/notifications',
 *     template: 'Notifications/item',
 *     data: ['notification' => $notification]
 * );
 *
 * // With layout
 * $update = ViewUpdate::create(
 *     topics: '/dashboard',
 *     template: 'Dashboard/stats',
 *     layout: 'ajax',
 *     data: ['stats' => $stats]
 * );
 *
 * // Private update
 * $update = ViewUpdate::create(
 *     topics: '/users/123/messages',
 *     element: 'Messages/item',
 *     data: ['message' => $message],
 *     private: true
 * );
 * ```
 */
class ViewUpdate extends Update
{
    /**
     * Create a new ViewUpdate instance
     *
     * @param array<string>|string $topics Topic(s) to publish to
     * @param string|null $template Template to render (e.g., 'Books/view')
     * @param string|null $element Element to render (e.g., 'Books/item')
     * @param array<string, mixed> $data View variables to pass to the template/element
     * @param string|null $layout Layout to use (null for no layout)
     * @param bool $private Whether this is a private update
     * @param string|null $id Optional event ID
     * @param string|null $type Optional event type
     * @param int|null $retry Optional retry delay in milliseconds
     * @throws \InvalidArgumentException
     */
    public static function create(
        string|array $topics,
        ?string $template = null,
        ?string $element = null,
        array $data = [],
        ?string $layout = null,
        bool $private = false,
        ?string $id = null,
        ?string $type = null,
        ?int $retry = null,
    ): self {
        if ($template === null && $element === null) {
            throw new InvalidArgumentException('Either template or element must be specified');
        }

        if ($template !== null && $element !== null) {
            throw new InvalidArgumentException('Cannot specify both template and element');
        }

        $renderedData = static::render($template, $element, $data, $layout);

        return new self(
            topics: $topics,
            data: $renderedData,
            private: $private,
            id: $id,
            type: $type,
            retry: $retry,
        );
    }

    /**
     * Render the view or element using ViewBuilder
     *
     * @param string|null $template Template to render
     * @param string|null $element Element to render
     * @param array<string, mixed> $data View variables
     * @param string|null $layout Layout to use
     * @return string Rendered output
     */
    protected static function render(
        ?string $template,
        ?string $element,
        array $data,
        ?string $layout,
    ): string {
        $builder = new ViewBuilder();

        // Set view class from configuration if specified
        $config = ConfigurationHelper::getConfig();
        if (isset($config['view_class'])) {
            $builder->setClassName($config['view_class']);
        }

        // Set view variables
        $builder->setVars($data);

        // Configure template or element
        if ($element !== null) {
            // For elements, we'll render them directly via the built view
            $view = $builder->build();

            return $view->element($element, $data);
        }

        // Configure template rendering
        assert($template !== null);
        $builder->setTemplate($template);

        // Configure layout
        if ($layout !== null) {
            $builder->setLayout($layout);
        } else {
            // Disable layout by default for updates
            $builder->disableAutoLayout();
        }

        // Build and render the view
        $view = $builder->build();

        return $view->render();
    }
}
