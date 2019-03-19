<?php

use Faker\Generator as Faker;
use Tests\Models\{
	Category,
	CustomCategory,
	Product,
	CategorySoftDelete
};

$factory->define(Category::class, function (Faker $faker) {
    return [
        'name' => $faker->word,
    ];
});

$factory->define(CustomCategory::class, function (Faker $faker) {
    return [
        'name' => $faker->word,
    ];
});

$factory->define(CategorySoftDelete::class, function (Faker $faker) {
    return [
        'name' => $faker->word,
    ];
});

$factory->define(Product::class, function (Faker $faker) {
    return [
        'name' => $faker->word,
    ];
});
