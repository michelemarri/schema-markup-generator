<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema\Types;

use Metodo\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * WebApplication Schema
 *
 * For web-based applications and SaaS platforms.
 * Inherits from SoftwareApplication but is specifically for web apps.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <plugins@metodo.dev>
 */
class WebApplicationSchema extends AbstractSchema
{
    public function getType(): string
    {
        return 'WebApplication';
    }

    public function getLabel(): string
    {
        return __('Web Application', 'schema-markup-generator');
    }

    public function getDescription(): string
    {
        return __('For web-based applications, SaaS platforms, and online tools. A subtype of SoftwareApplication specifically for browser-based software.', 'schema-markup-generator');
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

        // Browser requirements (specific to WebApplication)
        $browserRequirements = $this->getMappedValue($post, $mapping, 'browserRequirements');
        if ($browserRequirements) {
            $data['browserRequirements'] = is_array($browserRequirements)
                ? implode(', ', $browserRequirements)
                : $browserRequirements;
        }

        // Software version
        $softwareVersion = $this->getMappedValue($post, $mapping, 'softwareVersion');
        if ($softwareVersion) {
            $data['softwareVersion'] = $softwareVersion;
        }

        // Application URL / Install URL (for PWA or web app landing)
        $applicationUrl = $this->getMappedValue($post, $mapping, 'installUrl');
        if ($applicationUrl) {
            $data['installUrl'] = $applicationUrl;
        }

        // Features list
        $featureList = $this->getMappedValue($post, $mapping, 'featureList');
        if ($featureList) {
            $data['featureList'] = is_array($featureList)
                ? implode(', ', $featureList)
                : $featureList;
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

        // Memory requirements
        $memoryRequirements = $this->getMappedValue($post, $mapping, 'memoryRequirements');
        if ($memoryRequirements) {
            $data['memoryRequirements'] = $memoryRequirements;
        }

        // Permissions
        $permissions = $this->getMappedValue($post, $mapping, 'permissions');
        if ($permissions) {
            $data['permissions'] = is_array($permissions)
                ? implode(', ', $permissions)
                : $permissions;
        }

        // Countries available
        $countriesAvailable = $this->getMappedValue($post, $mapping, 'countriesSupported');
        if ($countriesAvailable) {
            $data['countriesSupported'] = is_array($countriesAvailable)
                ? implode(', ', $countriesAvailable)
                : $countriesAvailable;
        }

        // Content rating
        $contentRating = $this->getMappedValue($post, $mapping, 'contentRating');
        if ($contentRating) {
            $data['contentRating'] = $contentRating;
        }

        /**
         * Filter web application schema data
         */
        $data = apply_filters('smg_web_application_schema_data', $data, $post, $mapping);

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
        return ['applicationCategory', 'browserRequirements', 'screenshot', 'aggregateRating', 'author', 'featureList'];
    }

    public function getPropertyDefinitions(): array
    {
        return [
            'name' => [
                'type' => 'text',
                'description' => __('Web application name. Shown in software rich results.', 'schema-markup-generator'),
                'description_long' => __('The name of the web application. This is the primary identifier shown in software rich results and app listings. Use the official product name.', 'schema-markup-generator'),
                'example' => __('Figma, Notion, Google Docs, Trello', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/name',
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('What the web app does. Displayed in search results.', 'schema-markup-generator'),
                'description_long' => __('A description of what the web application does and its key features. This appears in search results and helps users understand the app\'s purpose and benefits.', 'schema-markup-generator'),
                'example' => __('A collaborative design platform that enables teams to create, prototype, and iterate on product designs in real-time.', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/description',
                'auto' => 'post_excerpt',
            ],
            'applicationCategory' => [
                'type' => 'select',
                'description' => __('App type. Required for proper categorization in searches.', 'schema-markup-generator'),
                'description_long' => __('The category of the web application. This helps search engines categorize your app and show it in relevant searches.', 'schema-markup-generator'),
                'example' => __('BusinessApplication for productivity tools, DesignApplication for creative tools', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/applicationCategory',
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
            'browserRequirements' => [
                'type' => 'text',
                'description' => __('Supported browsers (Chrome, Firefox, Safari, etc.). Web-app specific.', 'schema-markup-generator'),
                'description_long' => __('The browser requirements for the web application. This is specific to WebApplication and helps users know which browsers are supported.', 'schema-markup-generator'),
                'example' => __('Chrome 90+, Firefox 85+, Safari 14+, Edge 90+', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/browserRequirements',
            ],
            'softwareVersion' => [
                'type' => 'text',
                'description' => __('Current version number. Shows freshness of the software.', 'schema-markup-generator'),
                'description_long' => __('The current version number of the web application. Keeping this updated shows users the software is actively maintained.', 'schema-markup-generator'),
                'example' => __('2.5.1, 14.0, 2024.1.0', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/softwareVersion',
            ],
            'installUrl' => [
                'type' => 'url',
                'description' => __('URL to access/install the web app (signup page or app URL).', 'schema-markup-generator'),
                'description_long' => __('A URL where users can access or install the web application. This could be a signup page, app landing page, or direct app URL.', 'schema-markup-generator'),
                'example' => __('https://app.example.com, https://example.com/signup', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/installUrl',
            ],
            'featureList' => [
                'type' => 'text',
                'description' => __('List of features. Helps users understand capabilities.', 'schema-markup-generator'),
                'description_long' => __('A list of the main features of the web application. This helps users quickly understand what the app can do.', 'schema-markup-generator'),
                'example' => __('Real-time collaboration, Cloud sync, Export to PDF, Team management', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/featureList',
            ],
            'price' => [
                'type' => 'number',
                'description' => __('Price in specified currency. Use 0 for free apps.', 'schema-markup-generator'),
                'description_long' => __('The price of the web application. Use 0 for free apps (this will display "Free" in search results). For subscription-based software, use the starting price.', 'schema-markup-generator'),
                'example' => __('0 (free), 9.99, 29.99', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/price',
            ],
            'priceCurrency' => [
                'type' => 'text',
                'description' => __('Currency code (EUR, USD). Required when price is set.', 'schema-markup-generator'),
                'description_long' => __('The currency of the price in ISO 4217 format. Required whenever a price is specified.', 'schema-markup-generator'),
                'example' => __('EUR, USD, GBP', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/priceCurrency',
            ],
            'ratingValue' => [
                'type' => 'number',
                'description' => __('Average user rating (1-5). Displays stars in rich results.', 'schema-markup-generator'),
                'description_long' => __('The average user rating for the web application. Star ratings significantly influence user decisions and click-through rates in search results.', 'schema-markup-generator'),
                'example' => __('4.8, 4.5, 4.2', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/ratingValue',
            ],
            'ratingCount' => [
                'type' => 'number',
                'description' => __('Total number of ratings. Social proof indicator.', 'schema-markup-generator'),
                'description_long' => __('The total number of user ratings. Higher numbers provide stronger social proof and indicate application popularity.', 'schema-markup-generator'),
                'example' => __('15420, 125000, 2500000', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/ratingCount',
            ],
            'screenshot' => [
                'type' => 'image',
                'description' => __('App screenshot. Shown as preview image in search results.', 'schema-markup-generator'),
                'description_long' => __('A screenshot of the web application interface. This may be displayed as a preview image in search results, helping users understand what the app looks like.', 'schema-markup-generator'),
                'example' => __('https://example.com/images/webapp-screenshot.png', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/screenshot',
            ],
            'memoryRequirements' => [
                'type' => 'text',
                'description' => __('Minimum RAM required to run smoothly.', 'schema-markup-generator'),
                'description_long' => __('The minimum memory (RAM) required to run the web application smoothly. This helps users know if their device can handle the app.', 'schema-markup-generator'),
                'example' => __('2 GB RAM, 4 GB RAM', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/memoryRequirements',
            ],
            'permissions' => [
                'type' => 'text',
                'description' => __('Browser permissions required (camera, microphone, etc.).', 'schema-markup-generator'),
                'description_long' => __('The browser permissions required by the web application. This helps users understand what access the app needs.', 'schema-markup-generator'),
                'example' => __('Camera access, Microphone access, Location services, Notifications', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/permissions',
            ],
            'countriesSupported' => [
                'type' => 'text',
                'description' => __('Countries where the app is available.', 'schema-markup-generator'),
                'description_long' => __('The countries where the web application is available. Use if the app has geographic restrictions.', 'schema-markup-generator'),
                'example' => __('Worldwide, US and EU only, IT, DE, FR, ES', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/countriesSupported',
            ],
            'contentRating' => [
                'type' => 'text',
                'description' => __('Content rating for age appropriateness.', 'schema-markup-generator'),
                'description_long' => __('The content rating indicating age appropriateness. Useful for apps that may contain mature content.', 'schema-markup-generator'),
                'example' => __('Everyone, Teen, Mature 17+', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/contentRating',
            ],
        ];
    }
}

