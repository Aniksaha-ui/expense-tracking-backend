<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PortfolioProfileService
{
    public const TABLE = 'portfolio_profiles';

    private const JSON_FIELDS = ['socials', 'skills', 'stats'];

    /**
     * Return the single portfolio profile row, creating it with default content on first access.
     */
    public function getOrCreate(): object
    {
        $profile = DB::table(self::TABLE)->orderBy('id')->first();

        if ($profile) {
            return $profile;
        }

        DB::table(self::TABLE)->insert([
            'id' => 1,
            ...$this->encodeJsonFields(self::defaultContent()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table(self::TABLE)->where('id', 1)->first();
    }

    /**
     * Decode a json column value from the database into an array.
     */
    public static function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function defaultContent(): array
    {
        return [
            'name' => 'Anik Saha',
            'headline' => 'Software Engineer',
            'tagline' => 'I build fast, reliable and delightful web products — from pixel-perfect interfaces to scalable APIs.',
            'bio' => "I'm a full-stack software engineer based in Dhaka, Bangladesh. I enjoy turning complex problems into simple, "
                ."well-crafted products using React, TypeScript, Laravel and modern cloud tooling.\n\n"
                .'I care about clean architecture, performance and developer experience, and I love collaborating with teams '
                .'to ship features that make a real difference for users.',
            'location' => 'Dhaka, Bangladesh',
            'email' => 'sahaanik1045@gmail.com',
            'phone' => null,
            'avatar' => null,
            'resume_url' => null,
            'availability_status' => 'available',
            'availability_text' => 'Open to freelance projects and full-time opportunities.',
            'years_of_experience' => 3,
            'socials' => [
                ['platform' => 'github', 'url' => 'https://github.com/'],
                ['platform' => 'linkedin', 'url' => 'https://www.linkedin.com/'],
                ['platform' => 'email', 'url' => 'mailto:sahaanik1045@gmail.com'],
            ],
            'skills' => [
                ['group' => 'Frontend', 'items' => ['React', 'TypeScript', 'Next.js', 'Tailwind CSS', 'Angular']],
                ['group' => 'Backend', 'items' => ['Laravel', 'PHP', 'Node.js', 'REST APIs']],
                ['group' => 'Database', 'items' => ['MySQL', 'PostgreSQL', 'Redis']],
                ['group' => 'DevOps & Tools', 'items' => ['Docker', 'Git', 'Linux', 'CI/CD']],
            ],
            'stats' => [
                ['label' => 'Years of experience', 'value' => '3+'],
                ['label' => 'Projects delivered', 'value' => '20+'],
                ['label' => 'Technologies', 'value' => '15+'],
            ],
            'seo_title' => 'Anik Saha — Software Engineer',
            'seo_description' => 'Portfolio of Anik Saha, a software engineer from Dhaka, Bangladesh building modern web applications with React, TypeScript and Laravel.',
        ];
    }

    private function encodeJsonFields(array $data): array
    {
        foreach (self::JSON_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = json_encode(array_values($data[$field] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return $data;
    }
}
