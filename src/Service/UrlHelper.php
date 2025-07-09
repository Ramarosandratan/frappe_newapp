<?php

namespace App\Service;

/**
 * Helper service for URL encoding/decoding of ERPNext IDs
 */
class UrlHelper
{
    /**
     * Encode an ERPNext ID for use in URLs
     * ERPNext IDs can contain slashes and other special characters
     */
    public static function encodeId(string $id): string
    {
        // Use base64 encoding to safely handle all special characters
        return base64_encode($id);
    }

    /**
     * Decode an ERPNext ID from URL
     */
    public static function decodeId(string $encodedId): string
    {
        // First try base64 decode (new method)
        $decoded = base64_decode($encodedId, true);
        if ($decoded !== false) {
            return $decoded;
        }

        // Fallback to URL decode for backward compatibility
        return urldecode($encodedId);
    }

    /**
     * Check if an ID is already encoded
     */
    public static function isEncoded(string $id): bool
    {
        return base64_decode($id, true) !== false;
    }
}