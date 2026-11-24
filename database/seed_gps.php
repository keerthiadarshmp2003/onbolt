<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDbConnection();

// 1. Update Schools 16, 17, 18
$schools = [
    16 => [
        'name' => 'Global Public School - Brookes',
        'slug' => 'global-public-school-brookes',
        'code' => 'GPS-BRK',
        'location' => 'Trivandrum',
        'city' => 'Trivandrum',
        'description' => 'Global Public School - Brookes Wing official uniform portal. Approved institutional apparel, certified patterns, and sports kits.',
        'logo' => 'assets/images/schools/global-public-school/BROOKES/GPS Brookes Grey Polo Uniform.png',
        'classes' => 'Kindergarten (Pre-KG, LKG, UKG), Primary (Grade 1-5), Middle School (Grade 6-8), Senior Secondary (Grade 9-12)'
    ],
    17 => [
        'name' => 'Global Public School - CBSE',
        'slug' => 'global-public-school-cbse',
        'code' => 'GPS-CBSE',
        'location' => 'Trivandrum',
        'city' => 'Trivandrum',
        'description' => 'Global Public School - CBSE Wing official uniform portal. Certified daily uniforms, academic wear, and sports house kits.',
        'logo' => 'assets/images/schools/global-public-school/CBSE/GPS Brookes Grey Polo Uniform.png',
        'classes' => 'Primary (Grade 1-5), Middle School (Grade 6-8), Secondary (Grade 9-10), Senior Secondary (Grade 11-12)'
    ],
    18 => [
        'name' => 'Global Public School - International',
        'slug' => 'global-public-school-international',
        'code' => 'GPS-INT',
        'location' => 'Trivandrum',
        'city' => 'Trivandrum',
        'description' => 'Global Public School - International Wing official uniform portal. Modern institutional apparel, certified uniforms, and accessories.',
        'logo' => 'assets/images/schools/global-public-school/INTERNATIONAL/Light Grey Polo with GPS Badge.png',
        'classes' => 'Early Years, PYP (Grade 1-5), MYP (Grade 6-10), DP (Grade 11-12)'
    ]
];

$upSchoolStmt = $pdo->prepare("UPDATE schools SET name = ?, slug = ?, code = ?, location = ?, city = ?, description = ?, logo = ?, applicable_classes = ?, ordering_enabled = 1, enable_class_selection = 1, uniform_mode = 'common' WHERE id = ?");

foreach ($schools as $id => $s) {
    $upSchoolStmt->execute([
        $s['name'],
        $s['slug'],
        $s['code'],
        $s['location'],
        $s['city'],
        $s['description'],
        $s['logo'],
        $s['classes'],
        $id
    ]);
    echo "Updated School ID {$id}: {$s['name']}\n";
}

// 2. Clear old test products for schools 16, 17, 18 from school_products if any
$pdo->prepare("DELETE FROM school_products WHERE school_id IN (16, 17, 18)")->execute();

// 3. Define catalog products for each wing
$catalog = [
    // --- BROOKES (School ID 16) ---
    16 => [
        [
            'name' => 'GPS Brookes Grey Polo Uniform',
            'sku' => 'GPS-BRK-POLO',
            'price' => 450.00,
            'custom_price' => 399.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/BROOKES/GPS Brookes Grey Polo Uniform.png',
            'desc' => 'Approved GPS Brookes grey pique polo uniform with contrast collar and certified institutional stitching.',
            'is_mandatory' => 1
        ],
        [
            'name' => 'GPS Brookes Grey Shorts',
            'sku' => 'GPS-BRK-SHT',
            'price' => 400.00,
            'custom_price' => 350.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36',
            'image' => 'assets/images/schools/global-public-school/BROOKES/GPS Brookes Grey  Shorts.png',
            'desc' => 'Durable institutional grey shorts with adjustable elastic waist and reinforced pockets.',
            'is_mandatory' => 1
        ],
        [
            'name' => 'Girls Brookes Pinafore',
            'sku' => 'GPS-BRK-PIN',
            'price' => 600.00,
            'custom_price' => 550.00,
            'sizes' => '22, 24, 26, 28, 30, 32, 34, 36, 38',
            'image' => 'assets/images/schools/global-public-school/BROOKES/Girls brookes pinafore.jpeg',
            'desc' => 'Official pleated girls pinafore tailored to certified GPS Brookes specifications.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'White Uniform Shirt',
            'sku' => 'GPS-BRK-WSHIRT',
            'price' => 450.00,
            'custom_price' => 399.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/BROOKES/White Uniform Shirt.png',
            'desc' => 'Formal white cotton uniform shirt with structured collar and embroidered crest emblem.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'GPS Sports Uniform Cap',
            'sku' => 'GPS-BRK-CAP',
            'price' => 200.00,
            'custom_price' => 180.00,
            'sizes' => 'Regular, Adjustable',
            'image' => 'assets/images/schools/global-public-school/BROOKES/GPS Sports Uniform Cap.png',
            'desc' => 'Protective sun visor sports cap with embroidered GPS insignia.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'GPS Uniform Belt with Gunmetal Buckle',
            'sku' => 'GPS-BRK-BELT',
            'price' => 180.00,
            'custom_price' => 160.00,
            'sizes' => 'Small (24-28), Medium (30-34), Large (36-40)',
            'image' => 'assets/images/schools/global-public-school/BROOKES/Belt/GPS Uniform Belt with Gunmetal Buckle.png',
            'desc' => 'High-strength braided nylon belt with gunmetal quick-lock buckle.',
            'is_mandatory' => 1
        ],
        [
            'name' => 'Charcoal Ribbed Crew Socks Pair',
            'sku' => 'GPS-BRK-SOCK',
            'price' => 160.00,
            'custom_price' => 140.00,
            'sizes' => '2, 3, 4, 5, 6, 7',
            'image' => 'assets/images/schools/global-public-school/BROOKES/Socks/Charcoal Ribbed Crew Socks Pair.png',
            'desc' => 'Cushioned combed cotton ribbed crew socks for daily academic comfort.',
            'is_mandatory' => 1
        ],
        [
            'name' => 'Sports Black Shorts',
            'sku' => 'GPS-BRK-SPO-SHT',
            'price' => 400.00,
            'custom_price' => 350.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36',
            'image' => 'assets/images/schools/global-public-school/BROOKES/Sports/Black Shorts on Light Wood.png',
            'desc' => 'Lightweight breathable quick-dry sports athletic shorts.',
            'is_mandatory' => 1
        ],
        [
            'name' => 'Blue House Sports V-Neck T-Shirt',
            'sku' => 'GPS-BRK-SPO-BLU',
            'price' => 450.00,
            'custom_price' => 399.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/BROOKES/Sports/Blue V-Neck T-Shirt with “onbolt” Label.png',
            'desc' => 'Official Blue House inter-house sports jersey with moisture-wicking technology.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'Bright Green House Sports V-Neck T-Shirt',
            'sku' => 'GPS-BRK-SPO-GRN',
            'price' => 450.00,
            'custom_price' => 399.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/BROOKES/Sports/Bright Green Onbolt GPS T-Shirt.png',
            'desc' => 'Official Green House inter-house sports jersey with moisture-wicking technology.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'Red House Sports V-Neck T-Shirt',
            'sku' => 'GPS-BRK-SPO-RED',
            'price' => 450.00,
            'custom_price' => 399.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/BROOKES/Sports/Red V-Neck T-Shirt Mockup.png',
            'desc' => 'Official Red House inter-house sports jersey with moisture-wicking technology.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'Yellow House Sports V-Neck T-Shirt',
            'sku' => 'GPS-BRK-SPO-YEL',
            'price' => 450.00,
            'custom_price' => 399.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/BROOKES/Sports/Yellow V-Neck T-Shirt Product Mockup.png',
            'desc' => 'Official Yellow House inter-house sports jersey with moisture-wicking technology.',
            'is_mandatory' => 0
        ]
    ],

    // --- CBSE (School ID 17) ---
    17 => [
        [
            'name' => 'GPS CBSE Grey Polo Uniform',
            'sku' => 'GPS-CBSE-POLO',
            'price' => 450.00,
            'custom_price' => 399.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/CBSE/GPS Brookes Grey Polo Uniform.png',
            'desc' => 'Official GPS CBSE academic polo uniform with breathable micro-knit cotton.',
            'is_mandatory' => 1
        ],
        [
            'name' => 'GPS CBSE Grey Pinstripe Shorts',
            'sku' => 'GPS-CBSE-SHT',
            'price' => 400.00,
            'custom_price' => 350.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36',
            'image' => 'assets/images/schools/global-public-school/CBSE/GPS CBSE Grey Pinstripe Shorts.png',
            'desc' => 'Certified grey pinstripe shorts tailored for junior & middle school CBSE boys.',
            'is_mandatory' => 1
        ],
        [
            'name' => 'Grey T-Shirt with Green Stripes',
            'sku' => 'GPS-CBSE-TEE-GRN',
            'price' => 450.00,
            'custom_price' => 399.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/CBSE/grey tshirt green strips.png',
            'desc' => 'Grey activity t-shirt featuring distinctive GPS forest green shoulder accents.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'Girls Pinafore (CBSE)',
            'sku' => 'GPS-CBSE-PIN',
            'price' => 600.00,
            'custom_price' => 550.00,
            'sizes' => '22, 24, 26, 28, 30, 32, 34, 36, 38',
            'image' => 'assets/images/schools/global-public-school/CBSE/Girls Pinafore.jpeg',
            'desc' => 'Official pleated pinafore dress designed for GPS CBSE primary students.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'Trousers (Boys)',
            'sku' => 'GPS-CBSE-TRS-B',
            'price' => 650.00,
            'custom_price' => 580.00,
            'sizes' => '24, 26, 28, 30, 32, 34, 36, 38, 40, 42',
            'image' => 'assets/images/schools/global-public-school/CBSE/Trousers Boys.png',
            'desc' => 'Formal tailored school trousers with double-stitch hem and zip fly.',
            'is_mandatory' => 1
        ],
        [
            'name' => 'Trousers (Girls)',
            'sku' => 'GPS-CBSE-TRS-G',
            'price' => 650.00,
            'custom_price' => 580.00,
            'sizes' => '24, 26, 28, 30, 32, 34, 36, 38, 40, 42',
            'image' => 'assets/images/schools/global-public-school/CBSE/trousers girls.png',
            'desc' => 'Comfortable tailored trousers for senior girls with side pockets and belt loops.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'White Uniform Shirt (CBSE)',
            'sku' => 'GPS-CBSE-WSHIRT',
            'price' => 450.00,
            'custom_price' => 399.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/CBSE/White Uniform Shirt.png',
            'desc' => 'Crisp institutional white uniform shirt made of durable easy-care poly-cotton.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'GPS Sports Uniform Cap',
            'sku' => 'GPS-CBSE-CAP',
            'price' => 200.00,
            'custom_price' => 180.00,
            'sizes' => 'Regular, Adjustable',
            'image' => 'assets/images/schools/global-public-school/CBSE/GPS Sports Uniform Cap.png',
            'desc' => 'Official protective sun visor cap for sports and physical education.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'GPS Uniform Belt with Gunmetal Buckle',
            'sku' => 'GPS-CBSE-BELT',
            'price' => 180.00,
            'custom_price' => 160.00,
            'sizes' => 'Small (24-28), Medium (30-34), Large (36-40)',
            'image' => 'assets/images/schools/global-public-school/CBSE/Belt/GPS Uniform Belt with Gunmetal Buckle.png',
            'desc' => 'High-strength braided nylon belt with gunmetal quick-lock buckle.',
            'is_mandatory' => 1
        ],
        [
            'name' => 'Charcoal Ribbed Crew Socks Pair',
            'sku' => 'GPS-CBSE-SOCK',
            'price' => 160.00,
            'custom_price' => 140.00,
            'sizes' => '2, 3, 4, 5, 6, 7',
            'image' => 'assets/images/schools/global-public-school/CBSE/Socks/Charcoal Ribbed Crew Socks Pair.png',
            'desc' => 'Soft elasticated ribbed crew socks designed for all-day campus comfort.',
            'is_mandatory' => 1
        ],
        [
            'name' => 'Sports Black Shorts',
            'sku' => 'GPS-CBSE-SPO-SHT',
            'price' => 400.00,
            'custom_price' => 350.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36',
            'image' => 'assets/images/schools/global-public-school/CBSE/Sports/Black Shorts on Light Wood.png',
            'desc' => 'Lightweight athletic black shorts with drawstring waistband.',
            'is_mandatory' => 1
        ],
        [
            'name' => 'Blue House Sports V-Neck T-Shirt',
            'sku' => 'GPS-CBSE-SPO-BLU',
            'price' => 450.00,
            'custom_price' => 399.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/CBSE/Sports/Blue V-Neck T-Shirt with “onbolt” Label.png',
            'desc' => 'Blue House sports jersey featuring breathable active-dry fabric.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'Bright Green House Sports V-Neck T-Shirt',
            'sku' => 'GPS-CBSE-SPO-GRN',
            'price' => 450.00,
            'custom_price' => 399.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/CBSE/Sports/Bright Green Onbolt GPS T-Shirt.png',
            'desc' => 'Green House sports jersey featuring breathable active-dry fabric.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'Red House Sports V-Neck T-Shirt',
            'sku' => 'GPS-CBSE-SPO-RED',
            'price' => 450.00,
            'custom_price' => 399.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/CBSE/Sports/Red V-Neck T-Shirt Mockup.png',
            'desc' => 'Red House sports jersey featuring breathable active-dry fabric.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'Yellow House Sports V-Neck T-Shirt',
            'sku' => 'GPS-CBSE-SPO-YEL',
            'price' => 450.00,
            'custom_price' => 399.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/CBSE/Sports/Yellow V-Neck T-Shirt Product Mockup.png',
            'desc' => 'Yellow House sports jersey featuring breathable active-dry fabric.',
            'is_mandatory' => 0
        ]
    ],

    // --- INTERNATIONAL (School ID 18) ---
    18 => [
        [
            'name' => 'Light Grey Onbolt Polo Shirt',
            'sku' => 'GPS-INT-POLO-DSP',
            'price' => 480.00,
            'custom_price' => 420.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/INTERNATIONAL/Light Grey Onbolt Polo Shirt Product Display.png',
            'desc' => 'Premium light grey OnBolt polo uniform with fine knit collar for International curriculum.',
            'is_mandatory' => 1
        ],
        [
            'name' => 'Light Grey Polo with GPS Badge',
            'sku' => 'GPS-INT-POLO-BDG',
            'price' => 480.00,
            'custom_price' => 420.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/INTERNATIONAL/Light Grey Polo with GPS Badge.png',
            'desc' => 'Official GPS International Polo with embroidered school crest emblem.',
            'is_mandatory' => 1
        ],
        [
            'name' => 'White Uniform Shirt (International)',
            'sku' => 'GPS-INT-WSHIRT',
            'price' => 450.00,
            'custom_price' => 399.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/INTERNATIONAL/White Uniform Shirt.png',
            'desc' => 'Premium formal white uniform shirt with precision tailored stitching.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'Trousers (Boys - International)',
            'sku' => 'GPS-INT-TRS-B',
            'price' => 650.00,
            'custom_price' => 580.00,
            'sizes' => '24, 26, 28, 30, 32, 34, 36, 38, 40, 42',
            'image' => 'assets/images/schools/global-public-school/INTERNATIONAL/Trousers Boys.png',
            'desc' => 'Tailored charcoal formal trousers with comfort waistband for boys.',
            'is_mandatory' => 1
        ],
        [
            'name' => 'Trousers (Girls - International)',
            'sku' => 'GPS-INT-TRS-G',
            'price' => 650.00,
            'custom_price' => 580.00,
            'sizes' => '24, 26, 28, 30, 32, 34, 36, 38, 40, 42',
            'image' => 'assets/images/schools/global-public-school/INTERNATIONAL/trousers girls.png',
            'desc' => 'Tailored charcoal formal trousers with comfort waistband for girls.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'GPS Sports Uniform Cap',
            'sku' => 'GPS-INT-CAP',
            'price' => 200.00,
            'custom_price' => 180.00,
            'sizes' => 'Regular, Adjustable',
            'image' => 'assets/images/schools/global-public-school/INTERNATIONAL/GPS Sports Uniform Cap.png',
            'desc' => 'Official protective sun visor cap for sports and outdoor athletics.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'GPS Uniform Belt with Gunmetal Buckle',
            'sku' => 'GPS-INT-BELT',
            'price' => 180.00,
            'custom_price' => 160.00,
            'sizes' => 'Small (24-28), Medium (30-34), Large (36-40)',
            'image' => 'assets/images/schools/global-public-school/INTERNATIONAL/Belt/GPS Uniform Belt with Gunmetal Buckle.png',
            'desc' => 'Official heavy-duty uniform belt with laser-engraved gunmetal clasp.',
            'is_mandatory' => 1
        ],
        [
            'name' => 'Charcoal Ribbed Crew Socks Pair',
            'sku' => 'GPS-INT-SOCK',
            'price' => 160.00,
            'custom_price' => 140.00,
            'sizes' => '2, 3, 4, 5, 6, 7',
            'image' => 'assets/images/schools/global-public-school/INTERNATIONAL/Socks/Charcoal Ribbed Crew Socks Pair.png',
            'desc' => 'Comfortable ribbed crew socks tailored for daily academic wear.',
            'is_mandatory' => 1
        ],
        [
            'name' => 'Sports Black Shorts',
            'sku' => 'GPS-INT-SPO-SHT',
            'price' => 400.00,
            'custom_price' => 350.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36',
            'image' => 'assets/images/schools/global-public-school/INTERNATIONAL/Sports/Black Shorts on Light Wood.png',
            'desc' => 'Lightweight performance sports shorts with athletic fit.',
            'is_mandatory' => 1
        ],
        [
            'name' => 'Blue House Sports V-Neck T-Shirt',
            'sku' => 'GPS-INT-SPO-BLU',
            'price' => 450.00,
            'custom_price' => 399.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/INTERNATIONAL/Sports/Blue V-Neck T-Shirt with “onbolt” Label.png',
            'desc' => 'Official Blue House jersey tailored for high-movement athletics.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'Bright Green House Sports V-Neck T-Shirt',
            'sku' => 'GPS-INT-SPO-GRN',
            'price' => 450.00,
            'custom_price' => 399.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/INTERNATIONAL/Sports/Bright Green Onbolt GPS T-Shirt.png',
            'desc' => 'Official Green House jersey tailored for high-movement athletics.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'Red House Sports V-Neck T-Shirt',
            'sku' => 'GPS-INT-SPO-RED',
            'price' => 450.00,
            'custom_price' => 399.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/INTERNATIONAL/Sports/Red V-Neck T-Shirt Mockup.png',
            'desc' => 'Official Red House jersey tailored for high-movement athletics.',
            'is_mandatory' => 0
        ],
        [
            'name' => 'Yellow House Sports V-Neck T-Shirt',
            'sku' => 'GPS-INT-SPO-YEL',
            'price' => 450.00,
            'custom_price' => 399.00,
            'sizes' => '20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40',
            'image' => 'assets/images/schools/global-public-school/INTERNATIONAL/Sports/Yellow V-Neck T-Shirt Product Mockup.png',
            'desc' => 'Official Yellow House jersey tailored for high-movement athletics.',
            'is_mandatory' => 0
        ]
    ]
];

// Product insert/update statement
$prodStmt = $pdo->prepare("INSERT INTO products (category_id, name, slug, sku, description, short_description, price, is_kit, featured_image, available_sizes, status, created_at)
VALUES (1, ?, ?, ?, ?, ?, ?, 0, ?, ?, 1, NOW())
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    description = VALUES(description),
    short_description = VALUES(short_description),
    price = VALUES(price),
    featured_image = VALUES(featured_image),
    available_sizes = VALUES(available_sizes),
    status = 1");

$varStmt = $pdo->prepare("INSERT INTO product_variants (product_id, size, stock_quantity, status, created_at)
VALUES (?, ?, 50, 1, NOW())
ON DUPLICATE KEY UPDATE status = 1");

$spStmt = $pdo->prepare("INSERT INTO school_products (school_id, product_id, custom_price, is_mandatory, applicable_grades, status, created_at)
VALUES (?, ?, ?, ?, 'All Classes', 1, NOW())
ON DUPLICATE KEY UPDATE 
    custom_price = VALUES(custom_price),
    is_mandatory = VALUES(is_mandatory),
    status = 1");

$count = 0;
foreach ($catalog as $sId => $prods) {
    echo "--- Populating School ID {$sId} ---\n";
    foreach ($prods as $p) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $p['name'] . '-' . $sId)));
        $prodStmt->execute([
            $p['name'],
            $slug,
            $p['sku'],
            $p['desc'],
            $p['desc'],
            $p['price'],
            $p['image'],
            $p['sizes']
        ]);

        // Get product ID
        $pId = (int)$pdo->query("SELECT id FROM products WHERE sku = " . $pdo->quote($p['sku']))->fetchColumn();
        if ($pId <= 0) continue;

        // Insert variants
        $sizeList = array_map('trim', explode(',', $p['sizes']));
        foreach ($sizeList as $sz) {
            if (!empty($sz)) {
                $varStmt->execute([$pId, $sz]);
            }
        }

        // Link in school_products
        $spStmt->execute([
            $sId,
            $pId,
            $p['custom_price'],
            $p['is_mandatory']
        ]);

        echo "  [OK] #{$pId} {$p['name']} (Custom: Rs. {$p['custom_price']}, Sizes: {$p['sizes']})\n";
        $count++;
    }
}

echo "Successfully populated {$count} products across Global Public School wings!\n";
