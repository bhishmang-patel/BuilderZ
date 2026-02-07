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
        '#64748b', // Slate 500 (Backup/Neutral)
        
        // Expanded Palette for variety
        '#dc2626', // Red 600
        '#ea580c', // Orange 600
        '#d97706', // Amber 600
        '#65a30d', // Lime 600
        '#059669', // Emerald 600
        '#0891b2', // Cyan 600
        '#2563eb', // Blue 600
        '#4f46e5', // Indigo 600
        '#7c3aed', // Violet 600
        '#c026d3', // Fuchsia 600
        '#e11d48', // Rose 600
        '#475569', // Slate 600

        '#b91c1c', // Red 700
        '#c2410c', // Orange 700
        '#b45309', // Amber 700
        '#4d7c0f', // Lime 700
        '#047857', // Emerald 700
        '#0e7490', // Cyan 700
        '#1d4ed8', // Blue 700
        '#4338ca', // Indigo 700
        '#6d28d9', // Violet 700
        '#a21caf', // Fuchsia 700
        '#be123c', // Rose 700
        '#334155'  // Slate 700
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
     * Get a consistent color for a customer based on their ID or Name.
     * 
     * @param int|string $id The customer/party ID
     * @param string|null $name Optional customer name for fallback hashing
     * @return string The hex color code
     */
    public static function getCustomerColor($id, $name = null) {
        // If ID is valid and non-zero, use it with a specific offset for customers
        if (!empty($id) && is_numeric($id) && $id > 0) {
            return self::getColor($id, 7); // Offset 7 to be distinct from projects (offset 0)
        }
        
        // If ID is missing but Name is provided, hash the name
        if (!empty($name)) {
            return self::getColor($name, 7);
        }

        // Fallback
        return self::PALETTE[count(self::PALETTE) - 1]; // Slate
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
