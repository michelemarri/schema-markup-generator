<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema\Types;

use Metodo\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * FAQ Schema
 *
 * For FAQ pages with questions and answers.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <plugins@metodo.dev>
 */
class FAQSchema extends AbstractSchema
{
    public function getType(): string
    {
        return 'FAQPage';
    }

    public function getLabel(): string
    {
        return __('FAQ Page', 'schema-markup-generator');
    }

    public function getDescription(): string
    {
        return __('For pages with frequently asked questions. Enables FAQ rich results in search.', 'schema-markup-generator');
    }

    public function build(WP_Post $post, array $mapping = []): array
    {
        $data = $this->buildBase($post);

        // Get FAQ items from mapping
        $faqItems = $this->getMappedValue($post, $mapping, 'faqItems');

        if (is_array($faqItems) && !empty($faqItems)) {
            $data['mainEntity'] = $this->buildFAQItems($faqItems);
        } else {
            // Try to extract FAQs from content
            $data['mainEntity'] = $this->extractFAQsFromContent($post);
        }

        /**
         * Filter FAQ schema data
         */
        $data = apply_filters('smg_faq_schema_data', $data, $post, $mapping);

        return $this->cleanData($data);
    }

    /**
     * Build FAQ items from structured data
     */
    private function buildFAQItems(array $items): array
    {
        $faqItems = [];

        foreach ($items as $item) {
            if (empty($item['question']) || empty($item['answer'])) {
                continue;
            }

            $faqItems[] = [
                '@type' => 'Question',
                'name' => wp_strip_all_tags($item['question']),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ];
        }

        return $faqItems;
    }

    /**
     * Extract FAQs from post content (h2/h3 + paragraph pattern)
     */
    private function extractFAQsFromContent(WP_Post $post): array
    {
        $content = $post->post_content;
        $faqItems = [];

        // Pattern: heading followed by content until next heading
        $pattern = '/<h[23][^>]*>(.*?)<\/h[23]>\s*(.*?)(?=<h[23]|$)/is';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $question = wp_strip_all_tags($match[1]);
                $answer = wp_strip_all_tags($match[2]);

                // Skip if question doesn't look like a question
                if (empty($question) || empty($answer)) {
                    continue;
                }

                // Check if it looks like a question
                if (str_ends_with($question, '?') || strlen($answer) > 50) {
                    $faqItems[] = [
                        '@type' => 'Question',
                        'name' => $question,
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => trim($answer),
                        ],
                    ];
                }
            }
        }

        return $faqItems;
    }

    public function getRequiredProperties(): array
    {
        return ['mainEntity'];
    }

    public function getRecommendedProperties(): array
    {
        return [];
    }

    public function getPropertyDefinitions(): array
    {
        return [
            'faqItems' => [
                'type' => 'repeater',
                'description' => __('Question/Answer pairs. Enables expandable FAQ rich results in Google - major SERP real estate boost.', 'schema-markup-generator'),
                'fields' => [
                    'question' => ['type' => 'text', 'description' => __('The question text. Should match user search intent.', 'schema-markup-generator')],
                    'answer' => ['type' => 'textarea', 'description' => __('Complete answer. Keep concise but informative (50-300 words ideal).', 'schema-markup-generator')],
                ],
            ],
        ];
    }
}

