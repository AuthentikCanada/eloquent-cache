<?php

use Faker\Generator as Faker;
use Tests\Models\{Category, CustomCategory};

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
