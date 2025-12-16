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
 * @author  Michele Marri <plugins@metodo.dev>
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
        $data = $this->buildBase($post, $mapping);

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

        // Image (with fallback to custom fallback image or site favicon)
        $image = $this->getMappedValue($post, $mapping, 'image');
        if ($image) {
            $data['image'] = is_array($image) ? $image['url'] : $image;
        } else {
            $imageWithFallback = $this->getImageWithFallback($post);
            if ($imageWithFallback) {
                $data['image'] = $imageWithFallback['url'];
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
        return array_merge(self::getAdditionalTypeDefinition(), [
            'name' => [
                'type' => 'text',
                'description' => __('Full name. Displayed in author knowledge panels and bylines.', 'schema-markup-generator'),
                'description_long' => __('The full name of the person. This is the primary identifier displayed in author knowledge panels, bylines, and when this person is referenced as an author or creator.', 'schema-markup-generator'),
                'example' => __('John Smith, Dr. Jane Doe, Maria García López', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/name',
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('Professional bio. Supports E-E-A-T signals for content credibility.', 'schema-markup-generator'),
                'description_long' => __('A professional biography describing the person\'s background, expertise, and accomplishments. This supports E-E-A-T signals (Experience, Expertise, Authoritativeness, Trustworthiness) for content credibility.', 'schema-markup-generator'),
                'example' => __('Senior software engineer with 15 years of experience in web development. Author of three bestselling programming books and frequent speaker at tech conferences.', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/description',
                'auto' => 'post_excerpt',
            ],
            'image' => [
                'type' => 'image',
                'description' => __('Professional photo. Shown in author knowledge panels. Use clear headshot.', 'schema-markup-generator'),
                'description_long' => __('A professional photo of the person. For best results in Knowledge Panels, use a clear, high-quality headshot with the face clearly visible. Recommended minimum size is 96x96 pixels.', 'schema-markup-generator'),
                'example' => __('https://example.com/images/john-smith-headshot.jpg', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/image',
            ],
            'jobTitle' => [
                'type' => 'text',
                'description' => __('Professional title. Establishes expertise and authority.', 'schema-markup-generator'),
                'description_long' => __('The person\'s current job title or professional role. This establishes expertise and authority in their field and may be displayed in search results.', 'schema-markup-generator'),
                'example' => __('Chief Technology Officer, Senior Editor, Professor of Economics, Lead Designer', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/jobTitle',
            ],
            'worksFor' => [
                'type' => 'text',
                'description' => __('Employer/organization. Links person to institutional credibility.', 'schema-markup-generator'),
                'description_long' => __('The organization or company the person works for. This links the person to institutional credibility and helps Google understand professional affiliations.', 'schema-markup-generator'),
                'example' => __('Google, Harvard University, The New York Times, Acme Corporation', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/worksFor',
            ],
            'email' => [
                'type' => 'email',
                'description' => __('Contact email. Enables direct contact from search results.', 'schema-markup-generator'),
                'description_long' => __('The person\'s contact email address. This may enable direct contact options in search results and Knowledge Panels.', 'schema-markup-generator'),
                'example' => __('john.smith@example.com, contact@johndoe.com', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/email',
            ],
            'telephone' => [
                'type' => 'text',
                'description' => __('Phone number. Shown in contact information panels.', 'schema-markup-generator'),
                'description_long' => __('The person\'s contact phone number. Include the international dialing code if the person operates internationally.', 'schema-markup-generator'),
                'example' => __('(555) 123-4567, +1-800-555-1234', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/telephone',
            ],
            'sameAs' => [
                'type' => 'url',
                'description' => __('Social/professional profiles (LinkedIn, Twitter, etc.). Builds identity graph for knowledge panel.', 'schema-markup-generator'),
                'description_long' => __('URLs of official social media profiles and professional pages. This helps Google build an identity graph and Knowledge Panel by connecting the person\'s presence across different platforms.', 'schema-markup-generator'),
                'example' => __('https://linkedin.com/in/johnsmith, https://twitter.com/johnsmith, https://github.com/johnsmith', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/sameAs',
            ],
            'knowsAbout' => [
                'type' => 'text',
                'description' => __('Expertise topics (comma-separated). Helps AI understand author authority areas.', 'schema-markup-generator'),
                'description_long' => __('Topics, subjects, or areas the person has expertise in. This helps AI and search engines understand what subjects the person is authoritative about, improving content credibility signals.', 'schema-markup-generator'),
                'example' => __('Machine Learning, Python Programming, Web Development, SEO, Digital Marketing', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/knowsAbout',
            ],
            'alumniOf' => [
                'type' => 'text',
                'description' => __('Educational institutions attended. Adds credibility for academic topics.', 'schema-markup-generator'),
                'description_long' => __('Educational institutions the person attended or graduated from. This adds credibility, especially for academic, scientific, or professional content.', 'schema-markup-generator'),
                'example' => __('MIT, Harvard Business School, Stanford University, Oxford University', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/alumniOf',
            ],
        ]);
    }
}

