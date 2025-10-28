<?php

return [
    'hero' => [
        'image' => '/mockImages/hero-bg.png',
        'title' => 'مرحبًا بكم في R&J',
        'description' => 'نحن أكثر من مجرد صالون، نحن ملاذ لأولئك الذين يسعون لاحتضان تفردهم وإبراز ثقتهم بأنفسهم.',
        'button' => 'احجز موعدك',
        'bannerTexts' => ['أناقة', 'جمال', 'ثقة', 'أسلوب'],
    ],
    'about' => [
        'title' => 'تأسسنا بشغف للأناقة والفن، صالوننا هو ملاذ لمن يسعى لتعزيز جماله الطبيعي واحتضان أسلوبه الفريد.',
        'cardText' => 'كل خبير لدينا حاصل على شهادة CIBTEC.',
    ],
    'beautyServices' => [
        'title' => 'خدمات التجميل',
        'buttonText' => 'عرض المزيد',
        'featuredServiceIds' => [
            'hair-coloring', 'spa-treatments', 'bridal-makeup', 'make-up', 'kids', 'facial-skin'
        ],
    ],
    'products' => [
        'title' => 'نمنحك القوة لتبدين وتشعرين بأفضل ما لديكِ',
        'button' => 'تسوقي الآن',
        'featuredProductIds' => ['product-0', 'product-1'],
    ],
    'appointment' => [
        'title' => 'ادخلي عالم الأناقة والرقي المصمم خصيصًا لتلبية احتياجاتك ورغباتك الفريدة',
        'button' => 'احجز موعدك',
        'images' => [
            ['image' => '/mockImages/appointment1.jpg', 'label' => 'ديكور صالون أنيق'],
            ['image' => '/mockImages/appointment2.jpg', 'label' => 'علاج تجميلي احترافي'],
            ['image' => '/mockImages/appointment3.jpg', 'label' => 'تجربة صالون مريحة'],
        ],
    ],
    'reviews' => [
        'displayCount' => 6,
        'sortBy' => 'featured',
    ],
];
