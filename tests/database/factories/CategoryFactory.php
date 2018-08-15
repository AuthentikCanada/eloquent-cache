<?php

use Faker\Generator as Faker;
use Tests\Models\Category;

$factory->define(Category::class, function (Faker $faker) {
    return [
        'name' => $faker->word,
    ];
});
