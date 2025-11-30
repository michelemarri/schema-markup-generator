<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Schema\Types;

use flavor\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * WebSite Schema
 *
 * For website-level schema with SearchAction for sitelinks search box.
 *
 * @package flavor\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <info@metodo.dev>
 */
class WebSiteSchema extends AbstractSchema
{
    public function getType(): string
    {
        return 'WebSite';
    }

    public function getLabel(): string
    {
        return __('Website', 'schema-markup-generator');
    }

    public function getDescription(): string
    {
        return __('For website-level schema. Enables sitelinks search box in search results when SearchAction is included.', 'schema-markup-generator');
    }

    public function build(WP_Post $post, array $mapping = []): array
    {
        $data = $this->buildBase($post);

        // Core properties
        $data['@id'] = home_url('/#website');
        $data['url'] = home_url('/');
        $data['name'] = get_bloginfo('name');

        // Description
        $description = get_bloginfo('description');
        if ($description) {
            $data['description'] = $description;
        }

        // Language
        $data['inLanguage'] = get_bloginfo('language');

        // Publisher
        $data['publisher'] = [
            '@id' => home_url('/#organization'),
        ];

        // Search Action (for sitelinks search box)
        $enableSearch = $this->getMappedValue($post, $mapping, 'enableSearchAction') ?? true;
        if ($enableSearch) {
            $data['potentialAction'] = $this->buildSearchAction();
        }

        /**
         * Filter website schema data
         */
        $data = apply_filters('smg_website_schema_data', $data, $post, $mapping);

        return $this->cleanData($data);
    }

    /**
     * Build SearchAction for sitelinks search box
     */
    private function buildSearchAction(): array
    {
        return [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => home_url('/?s={search_term_string}'),
            ],
            'query-input' => 'required name=search_term_string',
        ];
    }

    public function getRequiredProperties(): array
    {
        return ['name', 'url'];
    }

    public function getRecommendedProperties(): array
    {
        return ['description', 'potentialAction', 'publisher'];
    }

    public function getPropertyDefinitions(): array
    {
        return [
            'name' => [
                'type' => 'text',
                'description' => __('Site name. Shown in Google Knowledge Panel and search results.', 'schema-markup-generator'),
                'auto' => 'site_name',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('Site tagline/description. Helps define brand identity in search.', 'schema-markup-generator'),
                'auto' => 'site_description',
            ],
            'enableSearchAction' => [
                'type' => 'boolean',
                'description' => __('Enables sitelinks search box in Google results. Shows search field under your listing.', 'schema-markup-generator'),
            ],
        ];
    }
}

