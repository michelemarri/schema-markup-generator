<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Schema\Types;

use flavor\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * SoftwareApplication Schema
 *
 * For software applications, mobile apps, and web apps.
 *
 * @package flavor\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <info@metodo.dev>
 */
class SoftwareAppSchema extends AbstractSchema
{
    public function getType(): string
    {
        return 'SoftwareApplication';
    }

    public function getLabel(): string
    {
        return __('Software Application', 'schema-markup-generator');
    }

    public function getDescription(): string
    {
        return __('For software applications, mobile apps, and web apps. Enables app rich results with ratings and download info.', 'schema-markup-generator');
    }

    public function build(WP_Post $post, array $mapping = []): array
    {
        $data = $this->buildBase($post);

        // Core properties
        $data['name'] = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
        $data['description'] = $this->getPostDescription($post);
        $data['url'] = $this->getPostUrl($post);

        // Image/screenshot
        $image = $this->getMappedValue($post, $mapping, 'screenshot');
        if ($image) {
            $data['screenshot'] = is_array($image) ? $image['url'] : $image;
        } else {
            $featuredImage = $this->getFeaturedImage($post);
            if ($featuredImage) {
                $data['screenshot'] = $featuredImage['url'];
            }
        }

        // Application category
        $applicationCategory = $this->getMappedValue($post, $mapping, 'applicationCategory');
        if ($applicationCategory) {
            $data['applicationCategory'] = $applicationCategory;
        }

        // Operating system
        $operatingSystem = $this->getMappedValue($post, $mapping, 'operatingSystem');
        if ($operatingSystem) {
            $data['operatingSystem'] = is_array($operatingSystem)
                ? implode(', ', $operatingSystem)
                : $operatingSystem;
        }

        // Software version
        $softwareVersion = $this->getMappedValue($post, $mapping, 'softwareVersion');
        if ($softwareVersion) {
            $data['softwareVersion'] = $softwareVersion;
        }

        // Download URL
        $downloadUrl = $this->getMappedValue($post, $mapping, 'downloadUrl');
        if ($downloadUrl) {
            $data['downloadUrl'] = $downloadUrl;
        }

        // Install URL (for web apps)
        $installUrl = $this->getMappedValue($post, $mapping, 'installUrl');
        if ($installUrl) {
            $data['installUrl'] = $installUrl;
        }

        // File size
        $fileSize = $this->getMappedValue($post, $mapping, 'fileSize');
        if ($fileSize) {
            $data['fileSize'] = $fileSize;
        }

        // Author/developer
        $author = $this->getMappedValue($post, $mapping, 'author');
        if ($author) {
            $data['author'] = [
                '@type' => 'Organization',
                'name' => is_array($author) ? ($author['name'] ?? '') : $author,
            ];
        } else {
            $data['author'] = [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
            ];
        }

        // Offers (pricing)
        $offers = $this->buildOffers($post, $mapping);
        if (!empty($offers)) {
            $data['offers'] = $offers;
        }

        // Aggregate rating
        $rating = $this->getMappedValue($post, $mapping, 'ratingValue');
        if ($rating) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (float) $rating,
                'ratingCount' => (int) ($this->getMappedValue($post, $mapping, 'ratingCount') ?: 1),
            ];
        }

        // Requirements
        $requirements = $this->getMappedValue($post, $mapping, 'softwareRequirements');
        if ($requirements) {
            $data['softwareRequirements'] = is_array($requirements)
                ? implode(', ', $requirements)
                : $requirements;
        }

        // Permissions
        $permissions = $this->getMappedValue($post, $mapping, 'permissions');
        if ($permissions) {
            $data['permissions'] = is_array($permissions)
                ? implode(', ', $permissions)
                : $permissions;
        }

        // Content rating
        $contentRating = $this->getMappedValue($post, $mapping, 'contentRating');
        if ($contentRating) {
            $data['contentRating'] = $contentRating;
        }

        /**
         * Filter software app schema data
         */
        $data = apply_filters('smg_software_schema_data', $data, $post, $mapping);

        return $this->cleanData($data);
    }

    /**
     * Build offers data
     */
    private function buildOffers(WP_Post $post, array $mapping): array
    {
        $price = $this->getMappedValue($post, $mapping, 'price');

        // Default to free if no price specified
        if ($price === null) {
            $price = 0;
        }

        $offers = [
            '@type' => 'Offer',
            'price' => (float) $price,
            'priceCurrency' => $this->getMappedValue($post, $mapping, 'priceCurrency') ?: 'EUR',
        ];

        if ((float) $price === 0.0) {
            $offers['price'] = 0;
        }

        $availability = $this->getMappedValue($post, $mapping, 'availability');
        if ($availability) {
            $offers['availability'] = 'https://schema.org/' . $availability;
        }

        return $offers;
    }

    public function getRequiredProperties(): array
    {
        return ['name', 'offers'];
    }

    public function getRecommendedProperties(): array
    {
        return ['applicationCategory', 'operatingSystem', 'screenshot', 'aggregateRating', 'author'];
    }

    public function getPropertyDefinitions(): array
    {
        return [
            'name' => [
                'type' => 'text',
                'description' => __('App/software name. Shown in software rich results.', 'schema-markup-generator'),
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('What the app does. Displayed in search results and app listings.', 'schema-markup-generator'),
                'auto' => 'post_excerpt',
            ],
            'applicationCategory' => [
                'type' => 'select',
                'description' => __('App type. Required for proper categorization in app searches.', 'schema-markup-generator'),
                'options' => [
                    'GameApplication',
                    'SocialNetworkingApplication',
                    'TravelApplication',
                    'ShoppingApplication',
                    'SportsApplication',
                    'LifestyleApplication',
                    'BusinessApplication',
                    'DesignApplication',
                    'DeveloperApplication',
                    'DriverApplication',
                    'EducationalApplication',
                    'HealthApplication',
                    'FinanceApplication',
                    'SecurityApplication',
                    'BrowserApplication',
                    'CommunicationApplication',
                    'MultimediaApplication',
                    'UtilitiesApplication',
                ],
            ],
            'operatingSystem' => [
                'type' => 'text',
                'description' => __('Supported platforms (Windows, macOS, iOS, Android, etc.). Filters search results.', 'schema-markup-generator'),
            ],
            'softwareVersion' => [
                'type' => 'text',
                'description' => __('Current version number. Shows freshness of the software.', 'schema-markup-generator'),
            ],
            'downloadUrl' => [
                'type' => 'url',
                'description' => __('Direct download or app store link. Enables download button in results.', 'schema-markup-generator'),
            ],
            'price' => [
                'type' => 'number',
                'description' => __('Price in specified currency. Use 0 for free apps.', 'schema-markup-generator'),
            ],
            'priceCurrency' => [
                'type' => 'text',
                'description' => __('Currency code (EUR, USD). Required when price is set.', 'schema-markup-generator'),
            ],
            'ratingValue' => [
                'type' => 'number',
                'description' => __('Average user rating (1-5). Displays stars in app rich results.', 'schema-markup-generator'),
            ],
            'ratingCount' => [
                'type' => 'number',
                'description' => __('Total number of ratings. Social proof indicator.', 'schema-markup-generator'),
            ],
            'screenshot' => [
                'type' => 'image',
                'description' => __('App screenshot. Shown as preview image in search results.', 'schema-markup-generator'),
            ],
        ];
    }
}

