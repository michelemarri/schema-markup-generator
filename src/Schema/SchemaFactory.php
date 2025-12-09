<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema;

use Metodo\SchemaMarkupGenerator\Schema\Types\ArticleSchema;
use Metodo\SchemaMarkupGenerator\Schema\Types\ProductSchema;
use Metodo\SchemaMarkupGenerator\Schema\Types\OrganizationSchema;
use Metodo\SchemaMarkupGenerator\Schema\Types\PersonSchema;
use Metodo\SchemaMarkupGenerator\Schema\Types\FAQSchema;
use Metodo\SchemaMarkupGenerator\Schema\Types\HowToSchema;
use Metodo\SchemaMarkupGenerator\Schema\Types\EventSchema;
use Metodo\SchemaMarkupGenerator\Schema\Types\RecipeSchema;
use Metodo\SchemaMarkupGenerator\Schema\Types\ReviewSchema;
use Metodo\SchemaMarkupGenerator\Schema\Types\BreadcrumbSchema;
use Metodo\SchemaMarkupGenerator\Schema\Types\WebSiteSchema;
use Metodo\SchemaMarkupGenerator\Schema\Types\WebPageSchema;
use Metodo\SchemaMarkupGenerator\Schema\Types\VideoObjectSchema;
use Metodo\SchemaMarkupGenerator\Schema\Types\CourseSchema;
use Metodo\SchemaMarkupGenerator\Schema\Types\LearningResourceSchema;
use Metodo\SchemaMarkupGenerator\Schema\Types\SoftwareAppSchema;
use Metodo\SchemaMarkupGenerator\Schema\Types\WebApplicationSchema;
use Metodo\SchemaMarkupGenerator\Schema\Types\FinancialProductSchema;

/**
 * Schema Factory
 *
 * Creates schema instances based on type identifier.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema
 * @author  Michele Marri <plugins@metodo.dev>
 */
class SchemaFactory
{
    /**
     * Registered schema types
     *
     * @var array<string, class-string<SchemaInterface>>
     */
    private array $types = [];

    /**
     * Schema instances cache
     *
     * @var array<string, SchemaInterface>
     */
    private array $instances = [];

    public function __construct()
    {
        $this->registerDefaultTypes();
    }

    /**
     * Register default schema types
     */
    private function registerDefaultTypes(): void
    {
        $defaultTypes = [
            // Content types
            'Article' => ArticleSchema::class,
            'BlogPosting' => ArticleSchema::class,
            'NewsArticle' => ArticleSchema::class,

            // Business types
            'Product' => ProductSchema::class,
            'Organization' => OrganizationSchema::class,
            'LocalBusiness' => OrganizationSchema::class,

            // Person
            'Person' => PersonSchema::class,

            // FAQ (standalone)
            'FAQPage' => FAQSchema::class,

            // Instructional
            'HowTo' => HowToSchema::class,
            'Event' => EventSchema::class,
            'Recipe' => RecipeSchema::class,
            'Review' => ReviewSchema::class,

            // Global schemas
            'BreadcrumbList' => BreadcrumbSchema::class,
            'WebSite' => WebSiteSchema::class,

            // Page types (all use WebPageSchema with different @type)
            'WebPage' => WebPageSchema::class,
            'AboutPage' => WebPageSchema::class,
            'ContactPage' => WebPageSchema::class,
            'CollectionPage' => WebPageSchema::class,
            'ItemPage' => WebPageSchema::class,
            'CheckoutPage' => WebPageSchema::class,
            'SearchResultsPage' => WebPageSchema::class,
            'ProfilePage' => WebPageSchema::class,
            'QAPage' => WebPageSchema::class,
            'RealEstateListing' => WebPageSchema::class,
            'MedicalWebPage' => WebPageSchema::class,

            // Media
            'VideoObject' => VideoObjectSchema::class,

            // Education
            'Course' => CourseSchema::class,
            'LearningResource' => LearningResourceSchema::class,

            // Technical
            'SoftwareApplication' => SoftwareAppSchema::class,
            'WebApplication' => WebApplicationSchema::class,

            // Financial
            'FinancialProduct' => FinancialProductSchema::class,
        ];

        foreach ($defaultTypes as $type => $class) {
            $this->types[$type] = $class;
        }

        /**
         * Filter to register custom schema types
         *
         * @param array $types Associative array of type => class
         */
        $this->types = apply_filters('smg_register_schema_types', $this->types);
    }

    /**
     * Create a schema instance by type
     *
     * @param string $type The schema type identifier
     * @return SchemaInterface|null
     */
    public function create(string $type): ?SchemaInterface
    {
        if (!isset($this->types[$type])) {
            return null;
        }

        // Return cached instance if available
        if (isset($this->instances[$type])) {
            return $this->instances[$type];
        }

        $class = $this->types[$type];

        if (!class_exists($class)) {
            return null;
        }

        $instance = new $class();

        // For types that share a class (like BlogPosting -> ArticleSchema)
        // we need to set the specific type
        if (method_exists($instance, 'setType')) {
            $instance->setType($type);
        }

        $this->instances[$type] = $instance;

        return $instance;
    }

    /**
     * Check if a schema type is registered
     */
    public function hasType(string $type): bool
    {
        return isset($this->types[$type]);
    }

    /**
     * Get all registered schema types
     *
     * @return array<string, string> Type => Label pairs
     */
    public function getTypes(): array
    {
        $types = [];

        foreach (array_keys($this->types) as $type) {
            $schema = $this->create($type);
            if ($schema) {
                $types[$type] = $schema->getLabel();
            }
        }

        return $types;
    }

    /**
     * Get all registered schema types with descriptions
     *
     * @return array<string, array> Type => [label, description]
     */
    public function getTypesWithDescriptions(): array
    {
        $types = [];

        foreach (array_keys($this->types) as $type) {
            $schema = $this->create($type);
            if ($schema) {
                $types[$type] = [
                    'label' => $schema->getLabel(),
                    'description' => $schema->getDescription(),
                ];
            }
        }

        return $types;
    }

    /**
     * Get schema types grouped by category
     *
     * @return array<string, array>
     */
    public function getTypesGrouped(): array
    {
        return [
            __('Content', 'schema-markup-generator') => [
                'Article' => __('Article', 'schema-markup-generator'),
                'BlogPosting' => __('Blog Post', 'schema-markup-generator'),
                'NewsArticle' => __('News Article', 'schema-markup-generator'),
                'WebPage' => __('Web Page', 'schema-markup-generator'),
            ],
            __('Business', 'schema-markup-generator') => [
                'Organization' => __('Organization', 'schema-markup-generator'),
                'LocalBusiness' => __('Local Business', 'schema-markup-generator'),
                'Product' => __('Product', 'schema-markup-generator'),
            ],
            __('People & Reviews', 'schema-markup-generator') => [
                'Person' => __('Person', 'schema-markup-generator'),
                'Review' => __('Review', 'schema-markup-generator'),
            ],
            __('Instructional', 'schema-markup-generator') => [
                'FAQPage' => __('FAQ Page', 'schema-markup-generator'),
                'HowTo' => __('How-To Guide', 'schema-markup-generator'),
                'Recipe' => __('Recipe', 'schema-markup-generator'),
                'Course' => __('Course', 'schema-markup-generator'),
                'LearningResource' => __('Learning Resource', 'schema-markup-generator'),
            ],
            __('Media & Events', 'schema-markup-generator') => [
                'Event' => __('Event', 'schema-markup-generator'),
                'VideoObject' => __('Video', 'schema-markup-generator'),
            ],
            __('Technical', 'schema-markup-generator') => [
                'SoftwareApplication' => __('Software Application', 'schema-markup-generator'),
                'WebApplication' => __('Web Application', 'schema-markup-generator'),
                'WebSite' => __('Website', 'schema-markup-generator'),
                'BreadcrumbList' => __('Breadcrumb', 'schema-markup-generator'),
            ],
            __('Financial', 'schema-markup-generator') => [
                'FinancialProduct' => __('Financial Product', 'schema-markup-generator'),
            ],
        ];
    }

    /**
     * Register a custom schema type
     *
     * @param string $type  The type identifier
     * @param string $class The fully qualified class name
     */
    public function registerType(string $type, string $class): void
    {
        if (!is_subclass_of($class, SchemaInterface::class)) {
            throw new \InvalidArgumentException(
                sprintf('Class %s must implement SchemaInterface', $class)
            );
        }

        $this->types[$type] = $class;

        // Clear instance cache for this type
        unset($this->instances[$type]);
    }

    /**
     * Unregister a schema type
     */
    public function unregisterType(string $type): void
    {
        unset($this->types[$type], $this->instances[$type]);
    }
}

