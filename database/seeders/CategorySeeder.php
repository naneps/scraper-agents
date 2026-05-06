<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Technology',
                'description' => 'Latest news and updates from the world of tech and innovation.',
            ],
            [
                'name' => 'Politics',
                'description' => 'Updates on government, policy, and political events.',
            ],
            [
                'name' => 'Sports',
                'description' => 'Breaking sports news, scores, and highlights.',
            ],
            [
                'name' => 'Business',
                'description' => 'Market updates, financial news, and economic analysis.',
            ],
            [
                'name' => 'Entertainment',
                'description' => 'Movies, music, celebrity news, and more.',
            ],
            [
                'name' => 'Health',
                'description' => 'Latest news in health, wellness, and medical research.',
            ],
        ];

        foreach ($categories as $category) {
            \App\Models\Category::create($category);
        }
    }
}
