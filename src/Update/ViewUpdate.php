<?php
declare(strict_types=1);

namespace Mercure\Update;

use Cake\View\ViewBuilder;
use InvalidArgumentException;
use Mercure\Internal\ConfigurationHelper;

/**
 * View Update Builder
 *
 * Fluent builder for creating Update instances from rendered CakePHP views or elements.
 * This class provides a clean, chainable API for configuring and rendering view updates.
 *
 * Example usage:
 * ```
 * // Render an element
 * $update = (new ViewUpdate('/books/1'))
 *     ->element('Books/item')
 *     ->viewVars(['book' => $book])
 *     ->build();
 *
 * // Render a template
 * $update = (new ViewUpdate('/notifications'))
 *     ->template('Notifications/item')
 *     ->viewVars(['notification' => $notification])
 *     ->build();
 *
 * // With layout
 * $update = (new ViewUpdate('/dashboard'))
 *     ->template('Dashboard/stats')
 *     ->layout('ajax')
 *     ->viewVars(['stats' => $stats])
 *     ->build();
 *
 * // With custom view options
 * $update = (new ViewUpdate('/books/1'))
 *     ->element('Books/item')
 *     ->viewVars(['book' => $book])
 *     ->viewOptions(['plugin' => 'MyPlugin', 'theme' => 'custom'])
 *     ->build();
 *
 * // Private update
 * $update = (new ViewUpdate('/users/123/messages'))
 *     ->element('Messages/item')
 *     ->viewVars(['message' => $message])
 *     ->private()
 *     ->build();
 *
 * // Multiple topics
 * $update = (new ViewUpdate(['/books/1', '/notifications']))
 *     ->element('Books/item')
 *     ->viewVars(['book' => $book])
 *     ->build();
 *
 * // Static create() method also supported
 * $update = ViewUpdate::create(
 *     topics: '/books/1',
 *     element: 'Books/item',
 *     viewVars: ['book' => $book]
 * );
 * ```
 */
class ViewUpdate extends AbstractUpdateBuilder
{
    protected ?string $template = null;

    protected ?string $element = null;

    /**
     * @var array<string, mixed>
     */
    protected array $viewVars = [];

    protected ?string $layout = null;

    /**
     * @var array<string, mixed>
     */
    protected array $viewOptions = [];

    protected ?ViewBuilder $viewBuilder = null;

    /**
     * Set the template to render
     *
     * @param string $template Template name (e.g., 'Books/view')
     * @return $this
     */
    public function template(string $template)
    {
        $this->template = $template;
        $this->element = null; // Clear element if set

        return $this;
    }

    /**
     * Set the element to render
     *
     * @param string $element Element name (e.g., 'Books/item')
     * @return $this
     */
    public function element(string $element)
    {
        $this->element = $element;
        $this->template = null; // Clear template if set

        return $this;
    }

    /**
     * Set view variables
     *
     * @param array<string, mixed> $viewVars View variables
     * @return $this
     */
    public function viewVars(mixed $viewVars)
    {
        $this->viewVars = $viewVars;

        return $this;
    }

    /**
     * Set a single view variable
     *
     * @param string $key Variable name
     * @param mixed $value Variable value
     * @return $this
     */
    public function set(string $key, mixed $value)
    {
        $this->viewVars[$key] = $value;

        return $this;
    }

    /**
     * Set the layout to use
     *
     * @param string|null $layout Layout name or null to disable layout
     * @return $this
     */
    public function layout(?string $layout)
    {
        if ($layout !== null) {
            $this->layout = $layout;
        }

        return $this;
    }

    /**
     * Set ViewBuilder options
     *
     * @param array<string, mixed> $options ViewBuilder options (plugin, theme, helpers, etc.)
     * @return $this
     */
    public function viewOptions(array $options)
    {
        if ($options !== []) {
            $this->viewOptions = $options;
        }

        return $this;
    }

    /**
     * Set a custom ViewBuilder instance
     *
     * Useful for testing or when you need complete control over view configuration.
     *
     * @param \Cake\View\ViewBuilder $builder ViewBuilder instance
     * @return $this
     */
    public function withViewBuilder(ViewBuilder $builder)
    {
        $this->viewBuilder = $builder;

        return $this;
    }

    /**
     * Build and return the Update instance
     *
     * @throws \InvalidArgumentException
     */
    public function build(): Update
    {
        $this->validate();

        $renderedData = $this->render();

        return $this->createUpdate($renderedData);
    }

    /**
     * Validate the configuration
     *
     * @throws \InvalidArgumentException
     */
    protected function validate(): void
    {
        parent::validate();

        if ($this->template === null && $this->element === null) {
            throw new InvalidArgumentException('Either template or element must be specified');
        }
    }

    /**
     * Render the view or element
     *
     * @return string Rendered output
     */
    protected function render(): string
    {
        $builder = $this->createViewBuilder();

        // Set view variables
        $builder->setVars($this->viewVars);

        // Configure template or element
        if ($this->element !== null) {
            // For elements, render them directly via the built view
            $view = $builder->build();

            return $view->element($this->element, $this->viewVars);
        }

        // Configure template rendering
        assert($this->template !== null);
        $builder->setTemplate($this->template);

        // Configure layout
        if ($this->layout !== null) {
            $builder->setLayout($this->layout);
        } else {
            // Disable layout by default for updates
            $builder->disableAutoLayout();
        }

        // Build and render the view
        $view = $builder->build();

        return $view->render();
    }

    /**
     * Create and configure a ViewBuilder instance
     */
    protected function createViewBuilder(): ViewBuilder
    {
        // Use custom builder if provided (for testing)
        if ($this->viewBuilder instanceof ViewBuilder) {
            return $this->viewBuilder;
        }

        $builder = new ViewBuilder();

        // Set view class from configuration if specified
        $config = ConfigurationHelper::getConfig();
        if (isset($config['view_class'])) {
            $builder->setClassName($config['view_class']);
        }

        // Apply view builder options
        if ($this->viewOptions !== []) {
            $builder->setOptions($this->viewOptions);
        }

        return $builder;
    }

    /**
     * Static create method for creating updates with a single call
     *
     * @param array<string>|string $topics Topic(s) to publish to
     * @param string|null $template Template to render (e.g., 'Books/view')
     * @param string|null $element Element to render (e.g., 'Books/item')
     * @param array<string, mixed> $viewVars View variables to pass to the template/element
     * @param string|null $layout Layout to use (null for no layout)
     * @param bool $private Whether this is a private update
     * @param string|null $id Optional event ID
     * @param string|null $type Optional event type
     * @param int|null $retry Optional retry delay in milliseconds
     * @param array<string, mixed> $viewOptions Options for ViewBuilder (plugin, theme, etc.)
     * @throws \InvalidArgumentException
     */
    public static function create(
        string|array $topics,
        ?string $template = null,
        ?string $element = null,
        array $viewVars = [],
        ?string $layout = null,
        bool $private = false,
        ?string $id = null,
        ?string $type = null,
        ?int $retry = null,
        array $viewOptions = [],
    ): Update {
        // Safe to use static here - allows subclasses to extend ViewUpdate
        /** @phpstan-ignore-next-line new.static */
        $builder = new static($topics);

        if ($template !== null) {
            $builder->template($template);
        }

        if ($element !== null) {
            $builder->element($element);
        }

        return $builder
            ->viewVars($viewVars)
            ->layout($layout)
            ->viewOptions($viewOptions)
            ->private($private)
            ->id($id)
            ->type($type)
            ->retry($retry)
            ->build();
    }
}
