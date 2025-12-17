<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema\Types;

use Metodo\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * Event Schema
 *
 * For events, conferences, and gatherings.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <plugins@metodo.dev>
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
        $data = $this->buildBase($post, $mapping);

        // Core properties
        $data['name'] = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
        $data['description'] = $this->getPostDescription($post);
        $data['url'] = $this->getPostUrl($post);

        // Image (with fallback to custom fallback image or site favicon)
        $image = $this->getImageWithFallback($post);
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
            'priceCurrency' => $this->getMappedValue($post, $mapping, 'priceCurrency') ?: $this->getSiteCurrency(),
            'url' => $this->getMappedValue($post, $mapping, 'ticketUrl') ?: $this->getPostUrl($post),
        ];

        // Category is required for Event tickets per Google guidelines
        // Valid values: primary, resale, combo
        $category = $this->getMappedValue($post, $mapping, 'offerCategory');
        $offers['category'] = $category ?: 'primary';

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
        return ['description', 'endDate', 'image', 'offers', 'offerCategory', 'organizer', 'performer'];
    }

    public function getPropertyDefinitions(): array
    {
        return array_merge(self::getAdditionalTypeDefinition(), [
            'name' => [
                'type' => 'text',
                'required' => true,
                'description' => __('Event title. Shown in Google Events rich results and calendar integration.', 'schema-markup-generator'),
                'description_long' => __('The name or title of the event. This is the primary text shown in Google Events search results and can appear in Google Calendar integrations. Be specific and include key details.', 'schema-markup-generator'),
                'example' => __('Web Summit 2025, Taylor Swift - The Eras Tour, Annual Marketing Conference', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/name',
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('Event summary. Displayed in event listings and search results.', 'schema-markup-generator'),
                'description_long' => __('A description of the event. Include key details about what attendees can expect, who should attend, and what they will learn or experience.', 'schema-markup-generator'),
                'example' => __('Join us for a full-day workshop on advanced SEO techniques, featuring hands-on exercises and networking opportunities.', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/description',
                'auto' => 'post_excerpt',
            ],
            'startDate' => [
                'type' => 'datetime',
                'required' => true,
                'description' => __('Required. When the event begins. Enables event rich results display.', 'schema-markup-generator'),
                'description_long' => __('The start date and time of the event in ISO 8601 format. This is required for event rich results. Include timezone information for accurate display across regions.', 'schema-markup-generator'),
                'example' => __('2025-06-15T09:00:00+02:00, 2025-12-01T19:30:00-05:00', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/startDate',
            ],
            'endDate' => [
                'type' => 'datetime',
                'description' => __('When the event ends. Recommended for multi-day or timed events.', 'schema-markup-generator'),
                'description_long' => __('The end date and time of the event. Recommended for all events, especially multi-day events or events with specific end times. Helps users plan attendance.', 'schema-markup-generator'),
                'example' => __('2025-06-15T17:00:00+02:00, 2025-12-03T22:00:00-05:00', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/endDate',
            ],
            'eventStatus' => [
                'type' => 'select',
                'description' => __('Current event state. Update if postponed/cancelled to maintain search accuracy.', 'schema-markup-generator'),
                'description_long' => __('The current status of the event. Keep this updated if plans change - Google uses this to show accurate information and can display "Cancelled" or "Postponed" badges in search.', 'schema-markup-generator'),
                'example' => __('EventScheduled, EventPostponed, EventCancelled', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/eventStatus',
                'options' => ['EventScheduled', 'EventCancelled', 'EventPostponed', 'EventRescheduled'],
            ],
            'eventAttendanceMode' => [
                'type' => 'select',
                'description' => __('In-person, virtual, or hybrid. Required for proper event categorization.', 'schema-markup-generator'),
                'description_long' => __('Indicates whether the event is online, offline (in-person), or a mix of both. This is especially important post-COVID as users often filter by attendance mode.', 'schema-markup-generator'),
                'example' => __('OfflineEventAttendanceMode (in-person), OnlineEventAttendanceMode (virtual)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/eventAttendanceMode',
                'options' => ['OfflineEventAttendanceMode', 'OnlineEventAttendanceMode', 'MixedEventAttendanceMode'],
            ],
            'locationName' => [
                'type' => 'text',
                'required' => true,
                'description' => __('Venue or platform name. Required for in-person events.', 'schema-markup-generator'),
                'description_long' => __('The name of the venue, location, or online platform where the event takes place. For online events, use the platform name (Zoom, YouTube Live, etc.).', 'schema-markup-generator'),
                'example' => __('Madison Square Garden, Zoom Webinar, Convention Center Hall A', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/location',
            ],
            'streetAddress' => [
                'type' => 'text',
                'description' => __('Physical venue address. Enables map integration in search results.', 'schema-markup-generator'),
                'description_long' => __('The street address of the venue. Including a complete address enables Google Maps integration in search results, making it easier for attendees to find the location.', 'schema-markup-generator'),
                'example' => __('4 Pennsylvania Plaza, Via Roma 123', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/streetAddress',
            ],
            'addressLocality' => [
                'type' => 'text',
                'description' => __('City name. Important for local event discovery.', 'schema-markup-generator'),
                'description_long' => __('The city where the event takes place. This is crucial for local event searches - users often search for "events in [city name]".', 'schema-markup-generator'),
                'example' => __('New York, Milano, London, Berlin', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/addressLocality',
            ],
            'price' => [
                'type' => 'number',
                'description' => __('Ticket/entry price. Use 0 for free events. Enables price display in results.', 'schema-markup-generator'),
                'description_long' => __('The ticket or entry price. Use 0 for free events (this will show "Free" in search results). For variable pricing, use the lowest available price.', 'schema-markup-generator'),
                'example' => __('0 (free), 49.99, 199.00', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/price',
            ],
            'priceCurrency' => [
                'type' => 'text',
                'description' => __('Currency code (EUR, USD). Required when price is set.', 'schema-markup-generator'),
                'description_long' => __('The currency of the ticket price in ISO 4217 format. Required whenever a price is specified.', 'schema-markup-generator'),
                'example' => __('EUR, USD, GBP', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/priceCurrency',
            ],
            'offerCategory' => [
                'type' => 'select',
                'description' => __('Ticket type. Required by Google for Event tickets.', 'schema-markup-generator'),
                'description_long' => __('The category of ticket offer. Google requires this for Event schema. Use "primary" for tickets from the original seller/organizer, "resale" for secondary market tickets, or "combo" for package deals.', 'schema-markup-generator'),
                'example' => __('primary (original seller), resale (secondary market), combo (packages)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/category',
                'options' => ['primary', 'resale', 'combo'],
                'auto' => 'primary',
                'auto_description' => __('Defaults to "primary" (tickets from original seller)', 'schema-markup-generator'),
            ],
            'organizer' => [
                'type' => 'text',
                'description' => __('Organization or person hosting the event. Adds credibility.', 'schema-markup-generator'),
                'description_long' => __('The organization or person responsible for organizing the event. This adds credibility and helps users identify trusted event organizers.', 'schema-markup-generator'),
                'example' => __('TechCrunch, Local Chamber of Commerce, John Smith Events', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/organizer',
            ],
            'performer' => [
                'type' => 'text',
                'description' => __('Speaker, artist, or performer name. Improves discovery for known personalities.', 'schema-markup-generator'),
                'description_long' => __('The performer, speaker, or artist at the event. Including well-known names can improve discoverability when users search for that person.', 'schema-markup-generator'),
                'example' => __('Tony Robbins, Coldplay, Dr. Jane Goodall', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/performer',
            ],
        ]);
    }
}

