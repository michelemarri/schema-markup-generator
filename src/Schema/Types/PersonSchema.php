<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema\Types;

use Metodo\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * Person Schema
 *
 * For author pages, team members, and personal profiles.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <info@metodo.dev>
 */
class PersonSchema extends AbstractSchema
{
    public function getType(): string
    {
        return 'Person';
    }

    public function getLabel(): string
    {
        return __('Person', 'schema-markup-generator');
    }

    public function getDescription(): string
    {
        return __('For author pages, team members, and personal profiles. Helps search engines understand who created content.', 'schema-markup-generator');
    }

    public function build(WP_Post $post, array $mapping = []): array
    {
        $data = $this->buildBase($post);

        // Core properties
        $data['name'] = $this->getMappedValue($post, $mapping, 'name')
            ?: html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');

        $data['url'] = $this->getPostUrl($post);

        // Description/bio
        $description = $this->getMappedValue($post, $mapping, 'description')
            ?: $this->getPostDescription($post);
        if ($description) {
            $data['description'] = $description;
        }

        // Image
        $image = $this->getMappedValue($post, $mapping, 'image');
        if ($image) {
            $data['image'] = is_array($image) ? $image['url'] : $image;
        } else {
            $featuredImage = $this->getFeaturedImage($post);
            if ($featuredImage) {
                $data['image'] = $featuredImage['url'];
            }
        }

        // Job title
        $jobTitle = $this->getMappedValue($post, $mapping, 'jobTitle');
        if ($jobTitle) {
            $data['jobTitle'] = $jobTitle;
        }

        // Works for (organization)
        $worksFor = $this->getMappedValue($post, $mapping, 'worksFor');
        if ($worksFor) {
            $data['worksFor'] = [
                '@type' => 'Organization',
                'name' => is_array($worksFor) ? ($worksFor['name'] ?? '') : $worksFor,
            ];
        }

        // Email
        $email = $this->getMappedValue($post, $mapping, 'email');
        if ($email) {
            $data['email'] = $email;
        }

        // Telephone
        $telephone = $this->getMappedValue($post, $mapping, 'telephone');
        if ($telephone) {
            $data['telephone'] = $telephone;
        }

        // Social profiles (sameAs)
        $sameAs = $this->buildSameAs($post, $mapping);
        if (!empty($sameAs)) {
            $data['sameAs'] = $sameAs;
        }

        // Address
        $address = $this->getMappedValue($post, $mapping, 'address');
        if ($address) {
            if (is_array($address)) {
                $data['address'] = array_merge(['@type' => 'PostalAddress'], $address);
            } else {
                $data['address'] = [
                    '@type' => 'PostalAddress',
                    'addressLocality' => $address,
                ];
            }
        }

        // Alumni of
        $alumniOf = $this->getMappedValue($post, $mapping, 'alumniOf');
        if ($alumniOf) {
            $data['alumniOf'] = [
                '@type' => 'Organization',
                'name' => is_array($alumniOf) ? ($alumniOf['name'] ?? '') : $alumniOf,
            ];
        }

        // Knows about (expertise)
        $knowsAbout = $this->getMappedValue($post, $mapping, 'knowsAbout');
        if ($knowsAbout) {
            $data['knowsAbout'] = is_array($knowsAbout) ? $knowsAbout : [$knowsAbout];
        }

        /**
         * Filter person schema data
         */
        $data = apply_filters('smg_person_schema_data', $data, $post, $mapping);

        return $this->cleanData($data);
    }

    /**
     * Build sameAs array from social profile fields
     */
    private function buildSameAs(WP_Post $post, array $mapping): array
    {
        $sameAs = [];

        // Check for sameAs array first
        $directSameAs = $this->getMappedValue($post, $mapping, 'sameAs');
        if (is_array($directSameAs)) {
            $sameAs = array_merge($sameAs, $directSameAs);
        } elseif ($directSameAs) {
            $sameAs[] = $directSameAs;
        }

        // Check individual social fields
        $socialFields = ['facebook', 'twitter', 'linkedin', 'instagram', 'youtube', 'github'];
        foreach ($socialFields as $field) {
            $url = $this->getMappedValue($post, $mapping, $field);
            if ($url) {
                $sameAs[] = $url;
            }
        }

        return array_filter(array_unique($sameAs));
    }

    public function getRequiredProperties(): array
    {
        return ['name'];
    }

    public function getRecommendedProperties(): array
    {
        return ['image', 'description', 'jobTitle', 'sameAs'];
    }

    public function getPropertyDefinitions(): array
    {
        return [
            'name' => [
                'type' => 'text',
                'description' => __('Full name. Displayed in author knowledge panels and bylines.', 'schema-markup-generator'),
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('Professional bio. Supports E-E-A-T signals for content credibility.', 'schema-markup-generator'),
                'auto' => 'post_excerpt',
            ],
            'image' => [
                'type' => 'image',
                'description' => __('Professional photo. Shown in author knowledge panels. Use clear headshot.', 'schema-markup-generator'),
            ],
            'jobTitle' => [
                'type' => 'text',
                'description' => __('Professional title. Establishes expertise and authority.', 'schema-markup-generator'),
            ],
            'worksFor' => [
                'type' => 'text',
                'description' => __('Employer/organization. Links person to institutional credibility.', 'schema-markup-generator'),
            ],
            'email' => [
                'type' => 'email',
                'description' => __('Contact email. Enables direct contact from search results.', 'schema-markup-generator'),
            ],
            'telephone' => [
                'type' => 'text',
                'description' => __('Phone number. Shown in contact information panels.', 'schema-markup-generator'),
            ],
            'sameAs' => [
                'type' => 'url',
                'description' => __('Social/professional profiles (LinkedIn, Twitter, etc.). Builds identity graph for knowledge panel.', 'schema-markup-generator'),
            ],
            'knowsAbout' => [
                'type' => 'text',
                'description' => __('Expertise topics (comma-separated). Helps AI understand author authority areas.', 'schema-markup-generator'),
            ],
            'alumniOf' => [
                'type' => 'text',
                'description' => __('Educational institutions attended. Adds credibility for academic topics.', 'schema-markup-generator'),
            ],
        ];
    }
}

