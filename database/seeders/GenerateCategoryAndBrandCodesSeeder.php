<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Brand;
use Carbon\Carbon;

class GenerateCategoryAndBrandCodesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->generateCodesForCategories();
        $this->generateCodesForBrands();
    }

    private function generateCodesForCategories(): void
    {
        $usedCodes = Category::whereNotNull('code')->pluck('code')->map(fn($c) => strtoupper($c))->toArray();

        Category::whereNull('code')
            ->orWhere('code_locked', false)
            ->chunk(100, function ($categories) use (&$usedCodes) {
                foreach ($categories as $category) {
                    $code = $this->generateUniqueCode($category->title, $usedCodes);

                    $category->update([
                        'code' => $code,
                        'code_generated_at' => Carbon::now(),
                    ]);

                    $usedCodes[] = $code;

                    echo "Category ID {$category->id} assigned code: {$code}\n";
                }
            });
    }

    private function generateCodesForBrands(): void
    {
        $usedCodes = Brand::whereNotNull('code')->pluck('code')->map(fn($c) => strtoupper($c))->toArray();

        Brand::whereNull('code')
            ->orWhere('code_locked', false)
            ->chunk(100, function ($brands) use (&$usedCodes) {
                foreach ($brands as $brand) {
                    $code = $this->generateUniqueCode($brand->title, $usedCodes);

                    $brand->update([
                        'code' => $code,
                        'code_generated_at' => Carbon::now(),
                    ]);

                    $usedCodes[] = $code;

                    echo "Brand ID {$brand->id} assigned code: {$code}\n";
                }
            });
    }

    private function generateUniqueCode(string $title, array $usedCodes): string
    {
        // Generate 3-letter uppercase code from title
        $slug = strtoupper(Str::slug($title));
        $slug = preg_replace('/[^A-Z]/', '', $slug);
        $base = substr($slug, 0, 3);

        // Fallback if base is too short
        if (strlen($base) < 3) {
            $base = strtoupper(Str::random(3));
        }

        $code = $base;
        $i = 1;

        // Ensure uniqueness
        while (in_array($code, $usedCodes)) {
            $suffix = strtoupper(base_convert($i, 10, 36));
            $code = substr($base, 0, 3 - strlen($suffix)) . $suffix;
            $code = str_pad($code, 3, 'X'); // Ensure 3 letters
            $i++;
        }

        return $code;
    }
}
