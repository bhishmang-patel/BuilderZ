<?php

class ColorHelper {
    // A curated palette of 12 distinct, modern colors (Tailwind-inspired)
    // These are designed to be visually pleasing and have good contrast with white text.
    private const PALETTE = [
        '#ef4444', // Red 500
        '#f97316', // Orange 500
        '#f59e0b', // Amber 500
        '#84cc16', // Lime 500
        '#10b981', // Emerald 500
        '#06b6d4', // Cyan 500
        '#3b82f6', // Blue 500
        '#6366f1', // Indigo 500
        '#8b5cf6', // Violet 500
        '#d946ef', // Fuchsia 500
        '#f43f5e', // Rose 500
        '#64748b'  // Slate 500 (Backup/Neutral)
    ];

    /**
     * Get a consistent color for a project based on its ID.
     * 
     * @param int|string $id The project ID
     * @return string The hex color code
     */
    public static function getProjectColor($id) {
        return self::getColor($id);
    }

    /**
     * Get a consistent color for a customer based on their ID.
     * 
     * @param int|string $id The customer/party ID
     * @return string The hex color code
     */
    public static function getCustomerColor($id) {
        // Use a different offset or salt if we want customers to have different colors from projects with same ID
        // For now, simple consistent hashing is fine.
        return self::getColor($id, 3); // Simple offset to differentiate from projects if IDs collide
    }

    /**
     * Core deterministic color picker.
     */
    private static function getColor($id, $offset = 0) {
        if (empty($id) || !is_numeric($id)) {
            // Fallback for missing/invalid IDs - maybe hash the string if it's a string?
            if (is_string($id) && !empty($id)) {
                $hash = crc32($id);
                $index = abs($hash + $offset) % count(self::PALETTE);
                return self::PALETTE[$index];
            }
            return self::PALETTE[count(self::PALETTE) - 1]; // Return Slate for unknowns
        }

        $index = ($id + $offset) % count(self::PALETTE);
        return self::PALETTE[$index];
    }

    /**
     * Get an initial letter from a name.
     */
    public static function getInitial($name) {
        if (empty($name)) return '?';
        return strtoupper(substr(trim($name), 0, 1));
    }
}
