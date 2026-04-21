<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SkillSeeder extends Seeder
{
    /**
     * 15 skills across 7 categories. UUIDs are deterministic via Str::uuid5 so
     * the seeder is idempotent and referenced by MemberSeeder / ProjectSeeder.
     */
    public function run(): void
    {
        foreach (self::skills() as [$name, $category]) {
            SkillModel::updateOrCreate(
                ['id' => self::skillId($name)],
                ['name' => $name, 'category' => $category],
            );
        }
    }

    public static function skillId(string $name): string
    {
        return (string) Str::uuid5(Str::uuid5Namespace('dns'), 'skill:' . $name);
    }

    /** @return array<int, array{0:string,1:string}> */
    public static function skills(): array
    {
        return [
            ['TypeScript', 'programming_language'],
            ['PHP', 'programming_language'],
            ['Go', 'programming_language'],
            ['Python', 'programming_language'],
            ['React', 'framework'],
            ['Next.js', 'framework'],
            ['Laravel', 'framework'],
            ['AWS', 'infrastructure'],
            ['Docker', 'infrastructure'],
            ['PostgreSQL', 'database'],
            ['MySQL', 'database'],
            ['UI Design', 'design'],
            ['UX Research', 'design'],
            ['Project Management', 'management'],
            ['Technical Writing', 'other'],
        ];
    }
}
