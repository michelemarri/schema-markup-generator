<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema;

/**
 * Schema Validator
 *
 * Validates schema.org structured data.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema
 * @author  Michele Marri <plugins@metodo.dev>
 */
class SchemaValidator
{
    /**
     * Required properties by schema type
     */
    private const REQUIRED_PROPERTIES = [
        'Article' => ['headline', 'author', 'datePublished', 'image'],
        'BlogPosting' => ['headline', 'author', 'datePublished', 'image'],
        'NewsArticle' => ['headline', 'author', 'datePublished', 'image'],
        'Product' => ['name', 'image', 'offers'],
        'Organization' => ['name'],
        'LocalBusiness' => ['name', 'address'],
        'Person' => ['name'],
        'FAQPage' => ['mainEntity'],
        'HowTo' => ['name', 'step'],
        'Event' => ['name', 'startDate', 'location'],
        'Recipe' => ['name', 'image', 'recipeIngredient', 'recipeInstructions'],
        'Review' => ['itemReviewed', 'reviewRating', 'author'],
        'VideoObject' => ['name', 'description', 'thumbnailUrl', 'uploadDate'],
        'Course' => ['name', 'description', 'provider'],
        'SoftwareApplication' => ['name', 'offers'],
        'WebSite' => ['name', 'url'],
        'WebPage' => ['name', 'url'],
        'BreadcrumbList' => ['itemListElement'],
    ];

    /**
     * Validate schema data
     *
     * @param array $data The schema data
     * @return array Validation result with 'valid', 'errors', and 'warnings'
     */
    public function validate(array $data): array
    {
        $errors = [];
        $warnings = [];

        // Check @context
        if (!isset($data['@context'])) {
            $errors[] = __('Missing @context property', 'schema-markup-generator');
        }

        // Check @type
        if (!isset($data['@type'])) {
            $errors[] = __('Missing @type property', 'schema-markup-generator');
            return [
                'valid' => false,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        $type = $data['@type'];

        // Check required properties
        if (isset(self::REQUIRED_PROPERTIES[$type])) {
            foreach (self::REQUIRED_PROPERTIES[$type] as $property) {
                if (!isset($data[$property]) || $this->isEmpty($data[$property])) {
                    $errors[] = sprintf(
                        __('Missing required property: %s', 'schema-markup-generator'),
                        $property
                    );
                }
            }
        }

        // Type-specific validation
        $typeErrors = $this->validateByType($type, $data);
        $errors = array_merge($errors, $typeErrors);

        // Check for common issues (warnings)
        $warnings = $this->checkCommonIssues($type, $data);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate graph of schemas
     *
     * @param array $graph Array of schema data
     * @return array Validation result
     */
    public function validateGraph(array $graph): array
    {
        $allErrors = [];
        $allWarnings = [];

        foreach ($graph as $index => $schema) {
            $result = $this->validate($schema);
            $type = $schema['@type'] ?? 'Unknown';

            foreach ($result['errors'] as $error) {
                $allErrors[] = "[{$type}] {$error}";
            }

            foreach ($result['warnings'] as $warning) {
                $allWarnings[] = "[{$type}] {$warning}";
            }
        }

        return [
            'valid' => empty($allErrors),
            'errors' => $allErrors,
            'warnings' => $allWarnings,
        ];
    }

    /**
     * Type-specific validation
     */
    private function validateByType(string $type, array $data): array
    {
        $errors = [];

        switch ($type) {
            case 'Article':
            case 'BlogPosting':
            case 'NewsArticle':
                $errors = $this->validateArticle($data);
                break;

            case 'Product':
                $errors = $this->validateProduct($data);
                break;

            case 'Event':
                $errors = $this->validateEvent($data);
                break;

            case 'Recipe':
                $errors = $this->validateRecipe($data);
                break;

            case 'Review':
                $errors = $this->validateReview($data);
                break;

            case 'VideoObject':
                $errors = $this->validateVideo($data);
                break;
        }

        return $errors;
    }

    /**
     * Validate Article schema
     */
    private function validateArticle(array $data): array
    {
        $errors = [];

        // Headline should be < 110 characters
        if (isset($data['headline']) && mb_strlen($data['headline']) > 110) {
            $errors[] = __('Headline should be less than 110 characters', 'schema-markup-generator');
        }

        // Date format validation
        if (isset($data['datePublished']) && !$this->isValidDate($data['datePublished'])) {
            $errors[] = __('datePublished is not in valid ISO 8601 format', 'schema-markup-generator');
        }

        if (isset($data['dateModified']) && !$this->isValidDate($data['dateModified'])) {
            $errors[] = __('dateModified is not in valid ISO 8601 format', 'schema-markup-generator');
        }

        return $errors;
    }

    /**
     * Validate Product schema
     */
    private function validateProduct(array $data): array
    {
        $errors = [];

        // Offers validation
        if (isset($data['offers'])) {
            $offers = is_array($data['offers']) && isset($data['offers']['@type'])
                ? [$data['offers']]
                : (is_array($data['offers']) ? $data['offers'] : []);

            foreach ($offers as $offer) {
                if (!isset($offer['price'])) {
                    $errors[] = __('Offer is missing price', 'schema-markup-generator');
                }
                if (!isset($offer['priceCurrency'])) {
                    $errors[] = __('Offer is missing priceCurrency', 'schema-markup-generator');
                }
            }
        }

        return $errors;
    }

    /**
     * Validate Event schema
     */
    private function validateEvent(array $data): array
    {
        $errors = [];

        // Start date validation
        if (isset($data['startDate']) && !$this->isValidDate($data['startDate'])) {
            $errors[] = __('startDate is not in valid ISO 8601 format', 'schema-markup-generator');
        }

        // End date should be after start date
        if (isset($data['startDate'], $data['endDate'])) {
            if (strtotime($data['endDate']) < strtotime($data['startDate'])) {
                $errors[] = __('endDate should be after startDate', 'schema-markup-generator');
            }
        }

        return $errors;
    }

    /**
     * Validate Recipe schema
     */
    private function validateRecipe(array $data): array
    {
        $errors = [];

        // Ingredients should be an array
        if (isset($data['recipeIngredient']) && !is_array($data['recipeIngredient'])) {
            $errors[] = __('recipeIngredient should be an array', 'schema-markup-generator');
        }

        // Instructions validation
        if (isset($data['recipeInstructions'])) {
            if (!is_array($data['recipeInstructions']) && !is_string($data['recipeInstructions'])) {
                $errors[] = __('recipeInstructions should be an array of HowToStep or a string', 'schema-markup-generator');
            }
        }

        return $errors;
    }

    /**
     * Validate Review schema
     */
    private function validateReview(array $data): array
    {
        $errors = [];

        // Rating validation
        if (isset($data['reviewRating'])) {
            $rating = $data['reviewRating'];

            if (isset($rating['ratingValue'])) {
                $value = (float) $rating['ratingValue'];
                $best = (float) ($rating['bestRating'] ?? 5);
                $worst = (float) ($rating['worstRating'] ?? 1);

                if ($value > $best || $value < $worst) {
                    $errors[] = __('ratingValue is outside the valid range', 'schema-markup-generator');
                }
            }
        }

        return $errors;
    }

    /**
     * Validate VideoObject schema
     */
    private function validateVideo(array $data): array
    {
        $errors = [];

        // Duration format validation
        if (isset($data['duration']) && !$this->isValidDuration($data['duration'])) {
            $errors[] = __('duration is not in valid ISO 8601 duration format', 'schema-markup-generator');
        }

        // URL validation
        if (isset($data['contentUrl']) && !filter_var($data['contentUrl'], FILTER_VALIDATE_URL)) {
            $errors[] = __('contentUrl is not a valid URL', 'schema-markup-generator');
        }

        if (isset($data['embedUrl']) && !filter_var($data['embedUrl'], FILTER_VALIDATE_URL)) {
            $errors[] = __('embedUrl is not a valid URL', 'schema-markup-generator');
        }

        return $errors;
    }

    /**
     * Check for common issues (warnings)
     */
    private function checkCommonIssues(string $type, array $data): array
    {
        $warnings = [];

        // Check for empty description
        if (isset($data['description']) && mb_strlen($data['description']) < 50) {
            $warnings[] = __('Description is very short. Consider adding more detail.', 'schema-markup-generator');
        }

        // Check for missing image
        if (!isset($data['image']) && in_array($type, ['Article', 'Product', 'Recipe', 'Event'], true)) {
            $warnings[] = __('Adding an image is recommended for rich results', 'schema-markup-generator');
        }

        // Check for missing publisher
        if (!isset($data['publisher']) && in_array($type, ['Article', 'BlogPosting', 'NewsArticle'], true)) {
            $warnings[] = __('Adding a publisher is recommended', 'schema-markup-generator');
        }

        return $warnings;
    }

    /**
     * Check if a value is empty
     */
    private function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === []) {
            return true;
        }

        if (is_array($value) && isset($value['@type']) && count($value) === 1) {
            return true;
        }

        return false;
    }

    /**
     * Validate ISO 8601 date format
     */
    private function isValidDate(string $date): bool
    {
        // Try to parse the date
        $timestamp = strtotime($date);
        return $timestamp !== false;
    }

    /**
     * Validate ISO 8601 duration format
     */
    private function isValidDuration(string $duration): bool
    {
        // ISO 8601 duration pattern
        $pattern = '/^P(?:\d+Y)?(?:\d+M)?(?:\d+W)?(?:\d+D)?(?:T(?:\d+H)?(?:\d+M)?(?:\d+S)?)?$/';
        return (bool) preg_match($pattern, $duration);
    }

    /**
     * Get Google Rich Results Test URL for schema
     *
     * @param string $url The page URL to test
     * @return string The Rich Results Test URL
     */
    public function getRichResultsTestUrl(string $url): string
    {
        return 'https://search.google.com/test/rich-results?url=' . urlencode($url);
    }

    /**
     * Get Schema.org Validator URL
     *
     * @param string $url The page URL to validate
     * @return string The validator URL
     */
    public function getSchemaValidatorUrl(string $url): string
    {
        return 'https://validator.schema.org/?url=' . urlencode($url);
    }
}

