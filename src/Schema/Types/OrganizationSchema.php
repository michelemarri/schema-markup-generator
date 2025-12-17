<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema\Types;

use Metodo\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * Organization Schema
 *
 * For organizations and local businesses.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <plugins@metodo.dev>
 */
class OrganizationSchema extends AbstractSchema
{
    private string $type = 'Organization';

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->type === 'LocalBusiness'
            ? __('Local Business', 'schema-markup-generator')
            : __('Organization', 'schema-markup-generator');
    }

    public function getDescription(): string
    {
        return __('For businesses and organizations. Includes contact info, address, and opening hours.', 'schema-markup-generator');
    }

    public function build(WP_Post $post, array $mapping = []): array
    {
        $data = $this->buildBase($post, $mapping);

        // Core properties
        $data['name'] = $this->getMappedValue($post, $mapping, 'name')
            ?: html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
        $data['description'] = $this->getPostDescription($post);
        $data['url'] = $this->getMappedValue($post, $mapping, 'url') ?: $this->getPostUrl($post);

        // Logo (with fallback to custom fallback image or site favicon)
        $logo = $this->getMappedValue($post, $mapping, 'logo');
        if ($logo) {
            $data['logo'] = is_array($logo) ? $logo['url'] : $logo;
        } else {
            $imageWithFallback = $this->getImageWithFallback($post);
            if ($imageWithFallback) {
                $data['logo'] = $imageWithFallback['url'];
            }
        }

        // Contact information
        $telephone = $this->getMappedValue($post, $mapping, 'telephone');
        if ($telephone) {
            $data['telephone'] = $telephone;
        }

        $email = $this->getMappedValue($post, $mapping, 'email');
        if ($email) {
            $data['email'] = $email;
        }

        // Address
        $address = $this->buildAddress($post, $mapping);
        if (!empty($address)) {
            $data['address'] = $address;
        }

        // Social profiles
        $sameAs = $this->getMappedValue($post, $mapping, 'sameAs');
        if (is_array($sameAs)) {
            $data['sameAs'] = array_filter($sameAs);
        } elseif ($sameAs) {
            $data['sameAs'] = [$sameAs];
        }

        // Local Business specific
        if ($this->type === 'LocalBusiness') {
            $data = $this->addLocalBusinessProperties($data, $post, $mapping);
        }

        /**
         * Filter organization schema data
         */
        $data = apply_filters('smg_organization_schema_data', $data, $post, $mapping);

        return $this->cleanData($data);
    }

    /**
     * Build address data
     */
    private function buildAddress(WP_Post $post, array $mapping): array
    {
        $address = [
            '@type' => 'PostalAddress',
        ];

        $streetAddress = $this->getMappedValue($post, $mapping, 'streetAddress');
        if ($streetAddress) {
            $address['streetAddress'] = $streetAddress;
        }

        $city = $this->getMappedValue($post, $mapping, 'addressLocality');
        if ($city) {
            $address['addressLocality'] = $city;
        }

        $region = $this->getMappedValue($post, $mapping, 'addressRegion');
        if ($region) {
            $address['addressRegion'] = $region;
        }

        $postalCode = $this->getMappedValue($post, $mapping, 'postalCode');
        if ($postalCode) {
            $address['postalCode'] = $postalCode;
        }

        $country = $this->getMappedValue($post, $mapping, 'addressCountry');
        if ($country) {
            $address['addressCountry'] = $country;
        }

        // Return empty if only @type
        if (count($address) === 1) {
            return [];
        }

        return $address;
    }

    /**
     * Add LocalBusiness specific properties
     */
    private function addLocalBusinessProperties(array $data, WP_Post $post, array $mapping): array
    {
        // Opening hours
        $openingHours = $this->getMappedValue($post, $mapping, 'openingHours');
        if ($openingHours) {
            $data['openingHours'] = is_array($openingHours) ? $openingHours : [$openingHours];
        }

        // Price range
        $priceRange = $this->getMappedValue($post, $mapping, 'priceRange');
        if ($priceRange) {
            $data['priceRange'] = $priceRange;
        }

        // Geo coordinates
        $latitude = $this->getMappedValue($post, $mapping, 'latitude');
        $longitude = $this->getMappedValue($post, $mapping, 'longitude');
        if ($latitude && $longitude) {
            $data['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
            ];
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

        return $data;
    }

    public function getRequiredProperties(): array
    {
        return ['name'];
    }

    public function getRecommendedProperties(): array
    {
        return ['description', 'logo', 'url', 'telephone', 'address'];
    }

    public function getPropertyDefinitions(): array
    {
        return array_merge(self::getAdditionalTypeDefinition(), [
            'name' => [
                'type' => 'text',
                'required' => true,
                'description' => __('Business/organization name. Shown in knowledge panels and local search.', 'schema-markup-generator'),
                'description_long' => __('The official name of the organization or business. This is the primary identifier shown in Google Knowledge Panels, local search results, and Maps.', 'schema-markup-generator'),
                'example' => __('Acme Corporation, The Coffee House, Smith & Associates Law Firm', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/name',
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('What the organization does. Helps search engines categorize your business.', 'schema-markup-generator'),
                'description_long' => __('A description of the organization, its mission, services, or products. This helps search engines understand and categorize your business correctly.', 'schema-markup-generator'),
                'example' => __('A leading provider of sustainable packaging solutions, serving businesses worldwide since 1995.', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/description',
                'auto' => 'post_excerpt',
            ],
            'logo' => [
                'type' => 'image',
                'description' => __('Official logo. Displayed in Google Knowledge Panel. Use high-res square image.', 'schema-markup-generator'),
                'description_long' => __('The official logo of the organization. For best results in Knowledge Panels, use a square image at least 112x112 pixels. The image should be on a transparent or white background.', 'schema-markup-generator'),
                'example' => __('https://example.com/images/logo.png', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/logo',
            ],
            'telephone' => [
                'type' => 'text',
                'description' => __('Primary phone. Enables click-to-call in mobile search results.', 'schema-markup-generator'),
                'description_long' => __('The primary contact phone number. Include the international dialing code for global businesses. This enables click-to-call functionality in mobile search results.', 'schema-markup-generator'),
                'example' => __('(555) 123-4567, +1-800-555-1234, +39 02 1234567', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/telephone',
            ],
            'email' => [
                'type' => 'email',
                'description' => __('Contact email. Shown in business information panels.', 'schema-markup-generator'),
                'description_long' => __('The primary contact email address for the organization. This may be displayed in business information panels and can enable direct email links.', 'schema-markup-generator'),
                'example' => __('info@example.com, contact@company.com', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/email',
            ],
            'streetAddress' => [
                'type' => 'text',
                'description' => __('Physical location. Required for local business rich results.', 'schema-markup-generator'),
                'description_long' => __('The street address of the organization. Required for local business rich results and Google Maps integration. Include street number and name.', 'schema-markup-generator'),
                'example' => __('123 Main Street, Suite 100, Via Roma 45', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/streetAddress',
            ],
            'addressLocality' => [
                'type' => 'text',
                'description' => __('City name. Critical for local search ranking.', 'schema-markup-generator'),
                'description_long' => __('The city or locality where the business is located. This is critical for local search rankings - users often search for "business type + city name".', 'schema-markup-generator'),
                'example' => __('New York, Milano, London, San Francisco', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/addressLocality',
            ],
            'addressRegion' => [
                'type' => 'text',
                'description' => __('State/Province/Region. Helps with regional search queries.', 'schema-markup-generator'),
                'description_long' => __('The state, province, or region where the business is located. Use standard abbreviations where applicable (e.g., CA for California, MI for Milano).', 'schema-markup-generator'),
                'example' => __('NY, California, Lombardia, Greater London', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/addressRegion',
            ],
            'postalCode' => [
                'type' => 'text',
                'description' => __('ZIP/Postal code. Improves local search precision.', 'schema-markup-generator'),
                'description_long' => __('The ZIP or postal code. This improves precision in local search results and helps Google Maps locate your business accurately.', 'schema-markup-generator'),
                'example' => __('10001, 20121, SW1A 1AA, 75001', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/postalCode',
            ],
            'addressCountry' => [
                'type' => 'text',
                'description' => __('Country code (e.g., IT, US). Required for international businesses.', 'schema-markup-generator'),
                'description_long' => __('The country where the business is located. Use ISO 3166-1 alpha-2 country codes (2-letter codes). Required for international businesses operating in multiple countries.', 'schema-markup-generator'),
                'example' => __('US, IT, GB, DE, FR', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/addressCountry',
            ],
            'openingHours' => [
                'type' => 'text',
                'description' => __('Business hours (e.g., Mo-Fr 09:00-17:00). Shown in local pack results.', 'schema-markup-generator'),
                'description_long' => __('The opening hours of the business. Use the format: "Mo-Fr 09:00-17:00" or specify each day separately. This information is displayed prominently in local search results.', 'schema-markup-generator'),
                'example' => __('Mo-Fr 09:00-18:00, Sa 10:00-14:00, Mo,We,Fr 08:00-20:00', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/openingHours',
            ],
            'priceRange' => [
                'type' => 'text',
                'description' => __('Cost indicator (€, €€, €€€). Helps users gauge affordability.', 'schema-markup-generator'),
                'description_long' => __('A price range indicator using currency symbols. Common formats are $, $$, $$$ or €, €€, €€€ where more symbols indicate higher prices. Helps users quickly assess affordability.', 'schema-markup-generator'),
                'example' => __('€, €€, €€€, $, $$, $$$', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/priceRange',
            ],
            'sameAs' => [
                'type' => 'url',
                'description' => __('Social profile URLs. Links your brand across platforms for knowledge panel.', 'schema-markup-generator'),
                'description_long' => __('URLs of official social media profiles and authoritative pages about the organization. This helps Google build a Knowledge Panel and connect your brand identity across platforms.', 'schema-markup-generator'),
                'example' => __('https://facebook.com/yourcompany, https://linkedin.com/company/yourcompany, https://twitter.com/yourcompany', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/sameAs',
            ],
            'latitude' => [
                'type' => 'text',
                'description' => __('GPS latitude. Enables map pin placement in search results.', 'schema-markup-generator'),
                'description_long' => __('The latitude coordinate of the business location. Combined with longitude, this enables precise map pin placement in Google Maps and search results.', 'schema-markup-generator'),
                'example' => __('40.7128, 45.4642, 51.5074', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/latitude',
            ],
            'longitude' => [
                'type' => 'text',
                'description' => __('GPS longitude. Required with latitude for map integration.', 'schema-markup-generator'),
                'description_long' => __('The longitude coordinate of the business location. Both latitude and longitude are required for accurate map integration.', 'schema-markup-generator'),
                'example' => __('-74.0060, 9.1900, -0.1276', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/longitude',
            ],
        ]);
    }
}

