<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneValidationService
{
    protected PhoneNumberUtil $phoneUtil;

    public function __construct()
    {
        $this->phoneUtil = PhoneNumberUtil::getInstance();
    }

    /**
     * Validate and parse a phone number.
     *
     * @param  string  $number  Raw phone number string (e.g. +1 305-555-0199 or 3055550199)
     * @param  string  $defaultRegion  2-letter ISO country code (e.g. 'US', 'GB', 'CA')
     * @return array{isValid: bool, e164: ?string, national: ?string, international: ?string, countryCode: ?string, error: ?string}
     */
    public function validateAndFormat(string $number, string $defaultRegion = 'US'): array
    {
        $cleaned = trim($number);
        if ($cleaned === '') {
            return [
                'isValid' => false,
                'e164' => null,
                'national' => null,
                'international' => null,
                'countryCode' => null,
                'error' => 'Phone number cannot be empty.',
            ];
        }

        try {
            $parsed = $this->phoneUtil->parse($cleaned, strtoupper($defaultRegion));
            $isValid = $this->phoneUtil->isValidNumber($parsed);

            if (! $isValid) {
                return [
                    'isValid' => false,
                    'e164' => null,
                    'national' => null,
                    'international' => null,
                    'countryCode' => null,
                    'error' => 'Invalid phone number format for specified region.',
                ];
            }

            return [
                'isValid' => true,
                'e164' => $this->phoneUtil->format($parsed, PhoneNumberFormat::E164),
                'national' => $this->phoneUtil->format($parsed, PhoneNumberFormat::NATIONAL),
                'international' => $this->phoneUtil->format($parsed, PhoneNumberFormat::INTERNATIONAL),
                'countryCode' => $this->phoneUtil->getRegionCodeForNumber($parsed),
                'error' => null,
            ];
        } catch (NumberParseException $e) {
            return [
                'isValid' => false,
                'e164' => null,
                'national' => null,
                'international' => null,
                'countryCode' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
