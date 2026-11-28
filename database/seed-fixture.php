<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/**
 * Independent expected seed fixture for database regression tests.
 *
 * Extracted from the Phase I static menu baseline (never from SQL, API, or DB):
 *   Source commit:   75cbb4ce30980d46fe683310f953879d528d07df (Phase I main)
 *   Source file:     index.html
 *   Git blob OID:    d288ae3d620f62e522a20c86e29e99e058b90960
 *   Raw SHA-256:     4a9fd02d2952947b9893dc87bd0fe64414480385839c867580b9aa863aac3a1f
 *
 * Maintenance note: when official dishes are expanded (e.g. from 10 to >=20),
 * update this fixture from the approved menu content specification independently
 * of database/tidebites.sql so tests verify SQL seed integrity rather than
 * comparing SQL against itself.
 */
return [
    [
        'id' => 1,
        'name' => 'Thai Basil Pork Rice',
        'description' => 'Savory minced pork with fragrant basil and vegetables, served over fluffy rice and topped with two sunny-side-up eggs. A hearty pick for a busy day.',
        'price' => '129.00',
        'category' => 'meals',
        'image_path' => 'assets/images/thai-basil-pork-rice.jpg',
        'image_alt' => 'Thai basil minced pork and vegetables served with rice and two fried eggs.',
        'sort_order' => 1,
    ],
    [
        'id' => 2,
        'name' => 'Chicken Oyakodon Bowl',
        'description' => 'A comforting rice bowl with tender chicken, egg, and sweet onions, finished with a sprinkle of green onions and sesame.',
        'price' => '139.00',
        'category' => 'meals',
        'image_path' => 'assets/images/chicken-oyakodon-bowl.jpg',
        'image_alt' => 'Chicken and egg rice bowl topped with green onions, sesame, and vegetables.',
        'sort_order' => 2,
    ],
    [
        'id' => 3,
        'name' => 'Taiwanese Fish Ball Noodle Soup',
        'description' => 'Springy fish balls, sliced chicken, noodles, and fresh greens in a light, savory broth. A warm bowl of comfort between classes.',
        'price' => '139.00',
        'category' => 'meals',
        'image_path' => 'assets/images/taiwanese-fish-ball-noodle-soup.jpg',
        'image_alt' => 'Taiwanese noodle soup with fish balls, sliced chicken, and green vegetables.',
        'sort_order' => 3,
    ],
    [
        'id' => 4,
        'name' => 'Roasted Chicken Set Meal',
        'description' => 'A golden roasted chicken leg served with steamed rice, vegetables, and tasty sides. Everything you need in one satisfying plate.',
        'price' => '169.00',
        'category' => 'meals',
        'image_path' => 'assets/images/roasted-chicken-set-meal.jpg',
        'image_alt' => 'Roasted chicken leg set with rice, vegetables, and small side dishes.',
        'sort_order' => 4,
    ],
    [
        'id' => 5,
        'name' => 'Tomato Udon Soup',
        'description' => 'Thick udon noodles in a tangy tomato broth with sliced meat, egg, and green onions. Bright, savory, and wonderfully comforting.',
        'price' => '159.00',
        'category' => 'meals',
        'image_path' => 'assets/images/tomato-udon-soup.jpg',
        'image_alt' => 'Tomato broth udon with sliced meat, egg, green onions, and fish cake.',
        'sort_order' => 5,
    ],
    [
        'id' => 6,
        'name' => 'Beef and Egg Noodles',
        'description' => 'Juicy beef slices and a sunny-side-up egg over a bowl of noodles. A filling favorite when you need an energy boost.',
        'price' => '179.00',
        'category' => 'meals',
        'image_path' => 'assets/images/beef-and-egg-noodles.jpg',
        'image_alt' => 'Beef slices and a fried egg served over noodles in a dark bowl.',
        'sort_order' => 6,
    ],
    [
        'id' => 7,
        'name' => 'Chilled Pork Noodle Bowl',
        'description' => 'A colorful bowl of chilled noodles with sliced pork, edamame, cucumber, lotus root, and sweet corn, finished with a savory sauce.',
        'price' => '149.00',
        'category' => 'meals',
        'image_path' => 'assets/images/chilled-pork-noodle-bowl.jpg',
        'image_alt' => 'Chilled noodles topped with pork, edamame, cucumber, lotus root, and corn.',
        'sort_order' => 7,
    ],
    [
        'id' => 8,
        'name' => 'Watermelon Snow Ice',
        'description' => 'Light, fluffy shaved milk ice topped with juicy watermelon cubes. A refreshing little escape on a sunny afternoon.',
        'price' => '99.00',
        'category' => 'desserts',
        'image_path' => 'assets/images/watermelon-snow-ice.jpg',
        'image_alt' => 'White shaved milk ice generously topped with red watermelon cubes.',
        'sort_order' => 8,
    ],
    [
        'id' => 9,
        'name' => 'Mango Snow Ice',
        'description' => 'Delicate ribbons of shaved milk ice piled high with sweet mango pieces and a fruity drizzle. Bright, tropical, and irresistible.',
        'price' => '109.00',
        'category' => 'desserts',
        'image_path' => 'assets/images/mango-snow-ice.jpg',
        'image_alt' => 'A bowl of yellow shaved milk ice topped with fresh mango chunks.',
        'sort_order' => 9,
    ],
    [
        'id' => 10,
        'name' => 'Peanut Tang Yuan',
        'description' => 'Soft and chewy glutinous tang yuan topped with crushed peanut powder and served in a sweet sauce. A cozy Taiwanese dessert.',
        'price' => '79.00',
        'category' => 'desserts',
        'image_path' => 'assets/images/peanut-rice-dumplings.jpg',
        'image_alt' => 'White glutinous tang yuan coated with peanut powder in a dark sweet sauce.',
        'sort_order' => 10,
    ],
];
