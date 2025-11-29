<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Schema\Types;

use flavor\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * Organization Schema
 *
 * For organizations and local businesses.
 *
 * @package flavor\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <info@metodo.dev>
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
        $data = $this->buildBase($post);

        // Core properties
        $data['name'] = $this->getMappedValue($post, $mapping, 'name')
            ?: html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
        $data['description'] = $this->getPostDescription($post);
        $data['url'] = $this->getMappedValue($post, $mapping, 'url') ?: $this->getPostUrl($post);

        // Logo
        $logo = $this->getMappedValue($post, $mapping, 'logo');
        if ($logo) {
            $data['logo'] = is_array($logo) ? $logo['url'] : $logo;
        } else {
            $image = $this->getFeaturedImage($post);
            if ($image) {
                $data['logo'] = $image['url'];
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
        return [
            'name' => [
                'type' => 'text',
                'description' => __('Organization name', 'schema-markup-generator'),
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('Description', 'schema-markup-generator'),
                'auto' => 'post_excerpt',
            ],
            'logo' => [
                'type' => 'image',
                'description' => __('Logo image', 'schema-markup-generator'),
            ],
            'telephone' => [
                'type' => 'text',
                'description' => __('Phone number', 'schema-markup-generator'),
            ],
            'email' => [
                'type' => 'email',
                'description' => __('Email address', 'schema-markup-generator'),
            ],
            'streetAddress' => [
                'type' => 'text',
                'description' => __('Street address', 'schema-markup-generator'),
            ],
            'addressLocality' => [
                'type' => 'text',
                'description' => __('City', 'schema-markup-generator'),
            ],
            'addressRegion' => [
                'type' => 'text',
                'description' => __('State/Province', 'schema-markup-generator'),
            ],
            'postalCode' => [
                'type' => 'text',
                'description' => __('Postal code', 'schema-markup-generator'),
            ],
            'addressCountry' => [
                'type' => 'text',
                'description' => __('Country', 'schema-markup-generator'),
            ],
            'openingHours' => [
                'type' => 'text',
                'description' => __('Opening hours (e.g., Mo-Fr 09:00-17:00)', 'schema-markup-generator'),
            ],
            'priceRange' => [
                'type' => 'text',
                'description' => __('Price range (e.g., €€)', 'schema-markup-generator'),
            ],
        ];
    }
}

