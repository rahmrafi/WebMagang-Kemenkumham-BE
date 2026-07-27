<?php

namespace App\Support;

/**
 * Helper untuk parsing data anggota dari format lama "Nama|NIM|Email".
 *
 * Format kolom member_x: "Nama Lengkap|NIM|email@domain.com"
 * Seluruh aplikasi harus menggunakan class ini — jangan parse manual dengan explode('|', ...).
 */
class MemberParser
{
    /**
     * Parse string pipe-separated menjadi array asosiatif.
     * Return null jika string kosong atau nama kosong.
     *
     * @param string|null $memberStr  Format: "Nama|NIM|email"
     * @return array{nama: string, nim: string, email: string}|null
     */
    public static function parse(?string $memberStr): ?array
    {
        if (!$memberStr || trim($memberStr) === '') {
            return null;
        }
        $parts = array_map('trim', explode('|', $memberStr));
        $nama  = $parts[0] ?? '';
        if ($nama === '') {
            return null;
        }
        return [
            'nama'  => ucwords(strtolower($nama)),
            'nim'   => $parts[1] ?? '',
            'email' => $parts[2] ?? '',
        ];
    }

    /**
     * Ambil hanya nama dari string member.
     *
     * @param string|null $memberStr
     */
    public static function parseName(?string $memberStr): string
    {
        $parts = explode('|', (string) $memberStr);
        $nama  = trim($parts[0] ?? '');
        return $nama !== '' ? ucwords(strtolower($nama)) : 'pendaftar';
    }

    /**
     * Parse array dari semua member_1..10 sebuah submission.
     *
     * @param object $submission  Eloquent model dengan atribut member_1..10
     * @return array[]  Array dari hasil parse() yang tidak null
     */
    public static function parseAll(object $submission): array
    {
        $members = [];
        for ($i = 1; $i <= 10; $i++) {
            $parsed = static::parse($submission->{"member_$i"});
            if ($parsed) {
                $members[] = $parsed;
            }
        }
        return $members;
    }
}
