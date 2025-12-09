<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema\Types;

use Metodo\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * Financial Product Schema
 *
 * For financial products like loans, mortgages, bank accounts, credit cards,
 * insurance policies, and investment products.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <plugins@metodo.dev>
 */
class FinancialProductSchema extends AbstractSchema
{
    public function getType(): string
    {
        return 'FinancialProduct';
    }

    public function getLabel(): string
    {
        return __('Financial Product', 'schema-markup-generator');
    }

    public function getDescription(): string
    {
        return __('For financial products like loans, mortgages, bank accounts, credit cards, insurance policies, and investment products.', 'schema-markup-generator');
    }

    public function build(WP_Post $post, array $mapping = []): array
    {
        $data = $this->buildBase($post, $mapping);

        // Core properties
        $data['name'] = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
        $data['description'] = $this->getPostDescription($post);
        $data['url'] = $this->getPostUrl($post);

        // Image
        $image = $this->getFeaturedImage($post);
        if ($image) {
            $data['image'] = $image['url'];
        }

        // Provider (bank, insurance company, financial institution)
        $provider = $this->getMappedValue($post, $mapping, 'provider');
        if ($provider) {
            $data['provider'] = [
                '@type' => 'Organization',
                'name' => is_array($provider) ? ($provider['name'] ?? '') : $provider,
            ];
            
            // Add provider URL if available
            $providerUrl = $this->getMappedValue($post, $mapping, 'providerUrl');
            if ($providerUrl) {
                $data['provider']['url'] = $providerUrl;
            }
        } else {
            // Default to site as provider
            $data['provider'] = [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url('/'),
            ];
        }

        // Interest Rate (e.g., 3.5 for 3.5%)
        $interestRate = $this->getMappedValue($post, $mapping, 'interestRate');
        if ($interestRate !== null && $interestRate !== '') {
            $data['interestRate'] = [
                '@type' => 'QuantitativeValue',
                'value' => (float) $interestRate,
                'unitText' => 'PERCENT',
            ];
        }

        // Annual Percentage Rate (TAEG/APR)
        $apr = $this->getMappedValue($post, $mapping, 'annualPercentageRate');
        if ($apr !== null && $apr !== '') {
            $data['annualPercentageRate'] = (float) $apr;
        }

        // Fees and Commissions
        $fees = $this->getMappedValue($post, $mapping, 'feesAndCommissionsSpecification');
        if ($fees) {
            $data['feesAndCommissionsSpecification'] = $fees;
        }

        // Category (e.g., Loan, CreditCard, SavingsAccount)
        $category = $this->getMappedValue($post, $mapping, 'category');
        if ($category) {
            $data['category'] = $category;
        }

        // Terms and Conditions URL
        $termsUrl = $this->getMappedValue($post, $mapping, 'termsOfService');
        if ($termsUrl) {
            $data['termsOfService'] = $termsUrl;
        }

        // Loan/Credit specific properties
        $loanAmount = $this->getMappedValue($post, $mapping, 'amount');
        if ($loanAmount) {
            $currency = $this->getMappedValue($post, $mapping, 'currency') ?: 'EUR';
            $data['amount'] = [
                '@type' => 'MonetaryAmount',
                'value' => (float) $loanAmount,
                'currency' => $currency,
            ];
        }

        // Loan term/duration
        $loanTerm = $this->getMappedValue($post, $mapping, 'loanTerm');
        if ($loanTerm) {
            $data['loanTerm'] = $this->formatDuration($loanTerm);
        }

        // Required collateral
        $collateral = $this->getMappedValue($post, $mapping, 'requiredCollateral');
        if ($collateral) {
            $data['requiredCollateral'] = $collateral;
        }

        // Offers (pricing)
        $offers = $this->buildOffers($post, $mapping);
        if (!empty($offers)) {
            $data['offers'] = $offers;
        }

        // Aggregate Rating
        $rating = $this->getMappedValue($post, $mapping, 'ratingValue');
        if ($rating) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (float) $rating,
                'ratingCount' => (int) ($this->getMappedValue($post, $mapping, 'ratingCount') ?: 1),
            ];
        }

        /**
         * Filter financial product schema data
         */
        $data = apply_filters('smg_financial_product_schema_data', $data, $post, $mapping);

        return $this->cleanData($data);
    }

    /**
     * Build offers data
     */
    private function buildOffers(WP_Post $post, array $mapping): ?array
    {
        $price = $this->getMappedValue($post, $mapping, 'price');
        
        // Price is optional for financial products
        if ($price === null) {
            return null;
        }

        $currency = $this->getMappedValue($post, $mapping, 'priceCurrency') ?: 'EUR';

        $offers = [
            '@type' => 'Offer',
            'price' => (float) $price,
            'priceCurrency' => $currency,
            'url' => $this->getPostUrl($post),
        ];

        $availability = $this->getMappedValue($post, $mapping, 'availability');
        if ($availability) {
            $offers['availability'] = 'https://schema.org/' . $availability;
        }

        return $offers;
    }

    /**
     * Format duration to ISO 8601 if not already formatted
     */
    private function formatDuration(mixed $duration): string
    {
        if (is_string($duration) && str_starts_with(strtoupper($duration), 'P')) {
            return strtoupper($duration);
        }

        // If numeric, assume months
        if (is_numeric($duration)) {
            return 'P' . (int) $duration . 'M';
        }

        // Try to parse common formats
        $duration = strtolower(trim((string) $duration));
        
        // Match patterns like "1 month", "12 months", "1 year", "30 years"
        if (preg_match('/^(\d+)\s*(month|year|week|day)s?$/i', $duration, $matches)) {
            $num = (int) $matches[1];
            $unit = strtoupper(substr($matches[2], 0, 1));
            return "P{$num}{$unit}";
        }

        // Return as-is with P prefix if doesn't start with P
        return 'P' . strtoupper($duration);
    }

    public function getRequiredProperties(): array
    {
        return ['name', 'provider'];
    }

    public function getRecommendedProperties(): array
    {
        return ['description', 'interestRate', 'annualPercentageRate', 'feesAndCommissionsSpecification', 'category'];
    }

    public function getPropertyDefinitions(): array
    {
        return array_merge(self::getAdditionalTypeDefinition(), [
            'name' => [
                'type' => 'text',
                'description' => __('Product name. Main identifier in search results.', 'schema-markup-generator'),
                'description_long' => __('The name of the financial product. This is the primary identifier shown in search results. Use clear, descriptive names that include the product type.', 'schema-markup-generator'),
                'example' => __('Personal Loan Plus, Premium Credit Card, Home Mortgage Fixed Rate', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/name',
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('Product summary. Key features and benefits.', 'schema-markup-generator'),
                'description_long' => __('A description of the financial product including key features, benefits, and eligibility requirements. This helps users understand the product before clicking.', 'schema-markup-generator'),
                'example' => __('Flexible personal loan with competitive rates, no early repayment fees, and instant approval for amounts up to €50,000.', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/description',
                'auto' => 'post_excerpt',
            ],
            'provider' => [
                'type' => 'text',
                'description' => __('Financial institution name. Bank, insurance company, etc.', 'schema-markup-generator'),
                'description_long' => __('The name of the organization providing this financial product. This is typically a bank, credit union, insurance company, or other financial institution.', 'schema-markup-generator'),
                'example' => __('Banca Intesa, UniCredit, Allianz, Generali', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/provider',
            ],
            'providerUrl' => [
                'type' => 'url',
                'description' => __('Provider website URL.', 'schema-markup-generator'),
                'description_long' => __('The official website URL of the financial institution providing this product.', 'schema-markup-generator'),
                'example' => __('https://www.bancaintesa.it, https://www.unicredit.it', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/url',
            ],
            'category' => [
                'type' => 'select',
                'description' => __('Product type. Helps categorize in searches.', 'schema-markup-generator'),
                'description_long' => __('The category of financial product. This helps search engines categorize your product and show it in relevant searches.', 'schema-markup-generator'),
                'example' => __('Loan, CreditCard, SavingsAccount, Insurance', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/category',
                'options' => [
                    'Loan',
                    'MortgageLoan',
                    'PersonalLoan',
                    'AutoLoan',
                    'StudentLoan',
                    'CreditCard',
                    'DebitCard',
                    'PrepaidCard',
                    'BankAccount',
                    'SavingsAccount',
                    'CheckingAccount',
                    'DepositAccount',
                    'Investment',
                    'Insurance',
                    'LifeInsurance',
                    'HealthInsurance',
                    'AutoInsurance',
                    'HomeInsurance',
                    'Pension',
                    'Annuity',
                ],
            ],
            'interestRate' => [
                'type' => 'number',
                'description' => __('Interest rate percentage (e.g., 3.5 for 3.5%).', 'schema-markup-generator'),
                'description_long' => __('The interest rate for the financial product expressed as a percentage. For loans, this is the rate charged. For savings, this is the rate earned.', 'schema-markup-generator'),
                'example' => __('3.5, 4.99, 0.5', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/interestRate',
            ],
            'annualPercentageRate' => [
                'type' => 'number',
                'description' => __('APR/TAEG - Total annual cost including fees.', 'schema-markup-generator'),
                'description_long' => __('The Annual Percentage Rate (APR), known as TAEG in Italy. This represents the total annual cost of the product including interest and fees, expressed as a percentage.', 'schema-markup-generator'),
                'example' => __('4.2, 5.5, 12.9', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/annualPercentageRate',
            ],
            'feesAndCommissionsSpecification' => [
                'type' => 'text',
                'description' => __('Details about fees and commissions.', 'schema-markup-generator'),
                'description_long' => __('A description of fees, commissions, and other charges associated with the product. Can be text or a URL to a fees page.', 'schema-markup-generator'),
                'example' => __('No annual fee, €2/month account maintenance, 1.5% foreign transaction fee', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/feesAndCommissionsSpecification',
            ],
            'amount' => [
                'type' => 'number',
                'description' => __('Loan/credit amount. Maximum or typical amount.', 'schema-markup-generator'),
                'description_long' => __('The loan or credit amount available. This could be the maximum amount, a typical amount, or a range. Used for loans and credit products.', 'schema-markup-generator'),
                'example' => __('50000, 250000, 10000', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/amount',
            ],
            'currency' => [
                'type' => 'text',
                'description' => __('Currency code (EUR, USD, GBP).', 'schema-markup-generator'),
                'description_long' => __('The currency for monetary values in ISO 4217 format.', 'schema-markup-generator'),
                'example' => __('EUR, USD, GBP, CHF', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/currency',
            ],
            'loanTerm' => [
                'type' => 'text',
                'description' => __('Loan duration (e.g., "30 years", "12 months", "P5Y").', 'schema-markup-generator'),
                'description_long' => __('The duration of the loan in ISO 8601 format or natural language. Examples: P30Y (30 years), P12M (12 months), "5 years", "60 months".', 'schema-markup-generator'),
                'example' => __('P30Y, P12M, 5 years, 60 months', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/loanTerm',
            ],
            'requiredCollateral' => [
                'type' => 'text',
                'description' => __('Required collateral or guarantee.', 'schema-markup-generator'),
                'description_long' => __('Assets that a borrower must pledge as security for the loan. Common for mortgages and secured loans.', 'schema-markup-generator'),
                'example' => __('Real estate property, Vehicle title, Cash deposit', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/requiredCollateral',
            ],
            'termsOfService' => [
                'type' => 'url',
                'description' => __('URL to terms and conditions.', 'schema-markup-generator'),
                'description_long' => __('A link to the full terms and conditions document for this financial product.', 'schema-markup-generator'),
                'example' => __('https://example.com/terms/loan-agreement', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/termsOfService',
            ],
            'price' => [
                'type' => 'number',
                'description' => __('Product price/fee (if applicable).', 'schema-markup-generator'),
                'description_long' => __('A fixed price or fee for the financial product, if applicable. Examples: annual card fee, account setup fee.', 'schema-markup-generator'),
                'example' => __('0, 50, 99.99', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/price',
            ],
            'priceCurrency' => [
                'type' => 'text',
                'description' => __('Price currency code (EUR, USD).', 'schema-markup-generator'),
                'description_long' => __('The currency of the price in ISO 4217 format.', 'schema-markup-generator'),
                'example' => __('EUR, USD, GBP', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/priceCurrency',
            ],
            'ratingValue' => [
                'type' => 'number',
                'description' => __('Average rating (1-5). Shows stars in search results.', 'schema-markup-generator'),
                'description_long' => __('The average customer rating for this financial product. Ratings can improve visibility and trust in search results.', 'schema-markup-generator'),
                'example' => __('4.5, 4.2, 3.8', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/ratingValue',
            ],
            'ratingCount' => [
                'type' => 'number',
                'description' => __('Total number of ratings.', 'schema-markup-generator'),
                'description_long' => __('The total number of ratings/reviews for this product.', 'schema-markup-generator'),
                'example' => __('150, 1200, 45', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/ratingCount',
            ],
        ]);
    }
}

