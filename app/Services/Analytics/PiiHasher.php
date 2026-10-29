<?php

namespace App\Services\Analytics;

/**
 * Meta/TikTok match customer identifiers server-side via SHA-256 hashes of
 * *normalized* values — each field has its own normalization rule, so a
 * single blanket "hash everything the same way" approach silently breaks
 * matching (1_0.txt §7: "සියලු fields සඳහා එකම SHA-256 ක්‍රමය යෙදීම
 * නිවැරදි නොවේ").
 */
class PiiHasher
{
    public function email(?string $email): ?string
    {
        if (blank($email)) {
            return null;
        }

        return hash('sha256', strtolower(trim($email)));
    }

    /**
     * Digits only, assumes a US number when no country code is present
     * (this storefront ships US orders only) — E.164 without the `+`.
     */
    public function phone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (blank($digits)) {
            return null;
        }

        if (strlen($digits) === 10) {
            $digits = '1'.$digits;
        }

        return hash('sha256', $digits);
    }

    public function zip(?string $zip): ?string
    {
        if (blank($zip)) {
            return null;
        }

        return hash('sha256', strtolower(trim(explode('-', $zip)[0])));
    }

    public function state(?string $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        return hash('sha256', strtolower(trim($state)));
    }
}
