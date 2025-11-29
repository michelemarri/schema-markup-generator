<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Schema\Types;

use flavor\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * Event Schema
 *
 * For events, conferences, and gatherings.
 *
 * @package flavor\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <info@metodo.dev>
 */
class EventSchema extends AbstractSchema
{
    public function getType(): string
    {
        return 'Event';
    }

    public function getLabel(): string
    {
        return __('Event', 'schema-markup-generator');
    }

    public function getDescription(): string
    {
        return __('For events, conferences, and gatherings. Enables event rich results with date, location, and tickets.', 'schema-markup-generator');
    }

    public function build(WP_Post $post, array $mapping = []): array
    {
        $data = $this->buildBase($post);

        // Core properties
        $data['name'] = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
        $data['description'] = $this->getPostDescription($post);
        $data['url'] = $this->getPostUrl($post);

        // Image
        $image = $this->getFeaturedImage($post);
        if ($image) {
            $data['image'] = $image['url'];
        }

        // Start date (required)
        $startDate = $this->getMappedValue($post, $mapping, 'startDate');
        if ($startDate) {
            $data['startDate'] = $this->formatDate($startDate);
        }

        // End date
        $endDate = $this->getMappedValue($post, $mapping, 'endDate');
        if ($endDate) {
            $data['endDate'] = $this->formatDate($endDate);
        }

        // Event status
        $eventStatus = $this->getMappedValue($post, $mapping, 'eventStatus');
        if ($eventStatus) {
            $data['eventStatus'] = 'https://schema.org/' . $eventStatus;
        }

        // Event attendance mode
        $attendanceMode = $this->getMappedValue($post, $mapping, 'eventAttendanceMode');
        if ($attendanceMode) {
            $data['eventAttendanceMode'] = 'https://schema.org/' . $attendanceMode;
        }

        // Location
        $location = $this->buildLocation($post, $mapping);
        if (!empty($location)) {
            $data['location'] = $location;
        }

        // Organizer
        $organizer = $this->getMappedValue($post, $mapping, 'organizer');
        if ($organizer) {
            $data['organizer'] = [
                '@type' => 'Organization',
                'name' => is_array($organizer) ? ($organizer['name'] ?? '') : $organizer,
                'url' => is_array($organizer) ? ($organizer['url'] ?? '') : '',
            ];
        }

        // Performer
        $performer = $this->getMappedValue($post, $mapping, 'performer');
        if ($performer) {
            $data['performer'] = [
                '@type' => 'Person',
                'name' => is_array($performer) ? ($performer['name'] ?? '') : $performer,
            ];
        }

        // Offers (tickets)
        $offers = $this->buildOffers($post, $mapping);
        if (!empty($offers)) {
            $data['offers'] = $offers;
        }

        /**
         * Filter event schema data
         */
        $data = apply_filters('smg_event_schema_data', $data, $post, $mapping);

        return $this->cleanData($data);
    }

    /**
     * Build location data
     */
    private function buildLocation(WP_Post $post, array $mapping): array
    {
        $locationType = $this->getMappedValue($post, $mapping, 'locationType') ?: 'Place';

        // Virtual event
        if ($locationType === 'VirtualLocation') {
            $url = $this->getMappedValue($post, $mapping, 'locationUrl');
            if ($url) {
                return [
                    '@type' => 'VirtualLocation',
                    'url' => $url,
                ];
            }
            return [];
        }

        // Physical location
        $location = [
            '@type' => 'Place',
        ];

        $name = $this->getMappedValue($post, $mapping, 'locationName');
        if ($name) {
            $location['name'] = $name;
        }

        // Address
        $address = [];
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

        if (!empty($address)) {
            $location['address'] = array_merge(['@type' => 'PostalAddress'], $address);
        }

        // Return empty if only @type
        if (count($location) === 1) {
            return [];
        }

        return $location;
    }

    /**
     * Build offers (tickets) data
     */
    private function buildOffers(WP_Post $post, array $mapping): array
    {
        $price = $this->getMappedValue($post, $mapping, 'price');

        if (!$price && $price !== 0 && $price !== '0') {
            return [];
        }

        $offers = [
            '@type' => 'Offer',
            'price' => (float) $price,
            'priceCurrency' => $this->getMappedValue($post, $mapping, 'priceCurrency') ?: 'EUR',
            'url' => $this->getMappedValue($post, $mapping, 'ticketUrl') ?: $this->getPostUrl($post),
        ];

        $availability = $this->getMappedValue($post, $mapping, 'availability');
        if ($availability) {
            $offers['availability'] = 'https://schema.org/' . $availability;
        }

        $validFrom = $this->getMappedValue($post, $mapping, 'validFrom');
        if ($validFrom) {
            $offers['validFrom'] = $this->formatDate($validFrom);
        }

        return $offers;
    }

    public function getRequiredProperties(): array
    {
        return ['name', 'startDate', 'location'];
    }

    public function getRecommendedProperties(): array
    {
        return ['description', 'endDate', 'image', 'offers', 'organizer', 'performer'];
    }

    public function getPropertyDefinitions(): array
    {
        return [
            'name' => [
                'type' => 'text',
                'description' => __('Event name', 'schema-markup-generator'),
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('Event description', 'schema-markup-generator'),
                'auto' => 'post_excerpt',
            ],
            'startDate' => [
                'type' => 'datetime',
                'description' => __('Start date and time', 'schema-markup-generator'),
            ],
            'endDate' => [
                'type' => 'datetime',
                'description' => __('End date and time', 'schema-markup-generator'),
            ],
            'eventStatus' => [
                'type' => 'select',
                'description' => __('Event status', 'schema-markup-generator'),
                'options' => ['EventScheduled', 'EventCancelled', 'EventPostponed', 'EventRescheduled'],
            ],
            'eventAttendanceMode' => [
                'type' => 'select',
                'description' => __('Attendance mode', 'schema-markup-generator'),
                'options' => ['OfflineEventAttendanceMode', 'OnlineEventAttendanceMode', 'MixedEventAttendanceMode'],
            ],
            'locationName' => [
                'type' => 'text',
                'description' => __('Venue name', 'schema-markup-generator'),
            ],
            'streetAddress' => [
                'type' => 'text',
                'description' => __('Street address', 'schema-markup-generator'),
            ],
            'addressLocality' => [
                'type' => 'text',
                'description' => __('City', 'schema-markup-generator'),
            ],
            'price' => [
                'type' => 'number',
                'description' => __('Ticket price', 'schema-markup-generator'),
            ],
            'priceCurrency' => [
                'type' => 'text',
                'description' => __('Currency code', 'schema-markup-generator'),
            ],
        ];
    }
}

