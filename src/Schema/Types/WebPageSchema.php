<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Schema\Types;

use flavor\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * WebPage Schema
 *
 * For generic web pages.
 *
 * @package flavor\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <info@metodo.dev>
 */
class WebPageSchema extends AbstractSchema
{
    /**
     * The specific page type (can be overridden)
     */
    private string $specificType = 'WebPage';

    /**
     * Page type labels
     */
    private const TYPE_LABELS = [
        'WebPage' => 'Web Page',
        'AboutPage' => 'About Page',
        'ContactPage' => 'Contact Page',
        'FAQPage' => 'FAQ Page',
        'CollectionPage' => 'Collection Page',
        'ItemPage' => 'Item Page',
        'CheckoutPage' => 'Checkout Page',
        'SearchResultsPage' => 'Search Results Page',
        'ProfilePage' => 'Profile Page',
        'QAPage' => 'Q&A Page',
        'RealEstateListing' => 'Real Estate Listing',
        'MedicalWebPage' => 'Medical Web Page',
    ];

    public function getType(): string
    {
        return $this->specificType;
    }

    /**
     * Set the specific page type
     */
    public function setType(string $type): void
    {
        $this->specificType = $type;
    }

    public function getLabel(): string
    {
        return __(self::TYPE_LABELS[$this->specificType] ?? 'Web Page', 'schema-markup-generator');
    }

    public function getDescription(): string
    {
        return __('For generic web pages. Provides basic page-level schema with author and organization info.', 'schema-markup-generator');
    }

    public function build(WP_Post $post, array $mapping = []): array
    {
        $data = $this->buildBase($post);

        // Core properties
        $data['@id'] = $this->getPostUrl($post) . '#webpage';
        $data['url'] = $this->getPostUrl($post);
        $data['name'] = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
        $data['description'] = $this->getPostDescription($post);

        // Dates
        $data['datePublished'] = $this->formatDate($post->post_date_gmt);
        $data['dateModified'] = $this->formatDate($post->post_modified_gmt);

        // Language
        $data['inLanguage'] = get_bloginfo('language');

        // Is part of website
        $data['isPartOf'] = [
            '@id' => home_url('/#website'),
        ];

        // Author
        $data['author'] = $this->getAuthor($post);

        // Featured image
        $image = $this->getFeaturedImage($post);
        if ($image) {
            $data['primaryImageOfPage'] = $image;
        }

        // Breadcrumb reference
        $data['breadcrumb'] = [
            '@id' => $this->getPostUrl($post) . '#breadcrumb',
        ];

        // Use the specific type set, or auto-detect if it's generic WebPage
        if ($this->specificType !== 'WebPage') {
            $data['@type'] = $this->specificType;
        } else {
            // Check for manual override via mapping
            $pageType = $this->getMappedValue($post, $mapping, 'pageType');
            if ($pageType) {
                $data['@type'] = $pageType;
            } else {
                // Auto-detect special pages
                $data['@type'] = $this->detectPageType($post);
            }
        }

        /**
         * Filter webpage schema data
         */
        $data = apply_filters('smg_webpage_schema_data', $data, $post, $mapping);

        return $this->cleanData($data);
    }

    /**
     * Detect special page types
     */
    private function detectPageType(WP_Post $post): string
    {
        $slug = $post->post_name;
        $title = strtolower($post->post_title);

        // Contact page
        if (str_contains($slug, 'contact') || str_contains($title, 'contact')) {
            return 'ContactPage';
        }

        // About page
        if (str_contains($slug, 'about') || str_contains($title, 'about') ||
            str_contains($slug, 'chi-siamo') || str_contains($title, 'chi siamo')) {
            return 'AboutPage';
        }

        // FAQ page
        if (str_contains($slug, 'faq') || str_contains($title, 'faq') ||
            str_contains($title, 'domande frequenti')) {
            return 'FAQPage';
        }

        // Profile page
        if (str_contains($slug, 'profile') || str_contains($title, 'profile')) {
            return 'ProfilePage';
        }

        // Collection/Archive
        if (str_contains($slug, 'archive') || str_contains($slug, 'collection')) {
            return 'CollectionPage';
        }

        // Search results
        if (str_contains($slug, 'search') || str_contains($title, 'search')) {
            return 'SearchResultsPage';
        }

        // Checkout
        if (str_contains($slug, 'checkout') || str_contains($title, 'checkout')) {
            return 'CheckoutPage';
        }

        return 'WebPage';
    }

    public function getRequiredProperties(): array
    {
        return ['name', 'url'];
    }

    public function getRecommendedProperties(): array
    {
        return ['description', 'datePublished', 'dateModified', 'author', 'isPartOf'];
    }

    public function getPropertyDefinitions(): array
    {
        return [
            'name' => [
                'type' => 'text',
                'description' => __('Page title', 'schema-markup-generator'),
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('Page description', 'schema-markup-generator'),
                'auto' => 'post_excerpt',
            ],
            'pageType' => [
                'type' => 'select',
                'description' => __('Page type', 'schema-markup-generator'),
                'options' => [
                    'WebPage',
                    'AboutPage',
                    'ContactPage',
                    'FAQPage',
                    'ProfilePage',
                    'CollectionPage',
                    'SearchResultsPage',
                    'CheckoutPage',
                ],
            ],
        ];
    }
}

