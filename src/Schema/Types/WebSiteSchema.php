<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema\Types;

use Metodo\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * WebSite Schema
 *
 * For website-level schema with SearchAction for sitelinks search box.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <plugins@metodo.dev>
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
                'description_long' => __('The official name of your website. This is displayed in Google\'s Knowledge Panel and can appear as the site name in search results (replacing the domain name).', 'schema-markup-generator'),
                'example' => __('Acme Corporation, The Daily Tech, My Awesome Blog', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/name',
                'auto' => 'site_name',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('Site tagline/description. Helps define brand identity in search.', 'schema-markup-generator'),
                'description_long' => __('A brief description or tagline for your website. This helps define your brand identity and helps search engines understand the site\'s overall purpose.', 'schema-markup-generator'),
                'example' => __('Your trusted source for tech news and reviews, Helping businesses grow since 1995', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/description',
                'auto' => 'site_description',
            ],
            'enableSearchAction' => [
                'type' => 'boolean',
                'description' => __('Enables sitelinks search box in Google results. Shows search field under your listing.', 'schema-markup-generator'),
                'description_long' => __('When enabled, adds SearchAction schema that can trigger the sitelinks search box in Google. This shows a search field directly in your search result, allowing users to search your site from Google.', 'schema-markup-generator'),
                'example' => __('true (enable), false (disable)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/potentialAction',
            ],
        ];
    }
}

