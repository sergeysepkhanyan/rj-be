<?php

return [
    'homepage' => [
        'hero' => [
            'image' => '/mockImages/hero-bg.png',
            'title' => 'Welcome to R&J',
            'description' => 'We are more than just a salon, we are a haven for those who seek to embrace their individuality and radiate confidence.',
            'button' => 'Book Appointment',
            'bannerTexts' => ['Elegance', 'Beauty', 'Confidence', 'Style'],
        ],
        'about' => [
            'title' => 'Founded with a passion for elegance and artistry, our salon is a sanctuary for those who seek to enhance their natural beauty and embrace their unique style.',
            'cardText' => 'Each and every our expert is holding CIBTEC certification',
        ],
        'beautyServices' => [
            'title' => 'Beauty Services',
            'buttonText' => 'View more',
            'featuredServiceIds' => [
                'hair-coloring', 'spa-treatments', 'bridal-makeup', 'make-up', 'kids', 'facial-skin'
            ],
        ],
        'products' => [
            'title' => 'Empowering You To Look And Feel Your Absolute Best',
            'button' => 'View shop',
            'featuredProductIds' => ['product-0', 'product-1'],
        ],
        'appointment' => [
            'title' => 'Step into a World of Elegance and Refinement Where Tailored Specifically to Your Unique Needs and Desires',
            'button' => 'Book Appointment',
            'images' => [
                ['image' => '/mockImages/appointment1.jpg', 'label' => 'Elegant salon interior'],
                ['image' => '/mockImages/appointment2.jpg', 'label' => 'Professional beauty treatment'],
                ['image' => '/mockImages/appointment3.jpg', 'label' => 'Relaxing salon experience'],
            ],
        ],
        'reviews' => [
            'displayCount' => 6,
            'sortBy' => 'featured',
        ],
    ],

    'aboutPage' => [
        'hero' => [
            'image' => '/mockImages/about-hero.jpg',
            'title' => 'About Us',
            'desc' => 'Discover the story behind our passion for beauty and excellence. We are dedicated to bringing you the finest beauty services with expertise and care.',
        ],
        'mainContent' => [
            'title' => 'Perfection Starts Here',
            'description' => 'We are a specialized beauty lounge regulated by the Dubai Health Authority and committed to providing top-quality services. Our experienced team specializes in hair, nails, skin & body, and semiperminent makeup including Microblading and Lips Perming. At our beauty lounge, your beauty and wellbeing are all that matters and we strive to enhance your natural beauty with our professional expertise.',
        ],
        'certification' => 'Each and every our expert is holding CIBTEC certification',
        'partners' => [
            'title' => 'Brands we work with',
            'description' => 'Offering you the best and highest quality of products!',
            'logos' => [
                ['name' => 'Fonola', 'image' => '/mockImages/fonola.png'],
                ['name' => "L'Oreal", 'image' => '/mockImages/loreal.png'],
                ['name' => 'MAC', 'image' => '/mockImages/mac.png'],
                ['name' => 'OPI', 'image' => '/mockImages/opi.png'],
                ['name' => 'Redken', 'image' => '/mockImages/redken.png'],
            ],
        ],
        'team' => [
            'title' => 'Our Experts',
            'description' => 'Meet the talented professionals who bring our vision to life with dedication and expertise.',
            'backgroundImage' => '/mockImages/expert-background.png',
            'expertIds' => ['expert-1', 'expert-2', 'expert-3', 'expert-4', 'expert-5', 'expert-6'],
        ],
        'reviews' => [
            'displayCount' => 6,
            'sortBy' => 'featured',
        ],
    ],

    'blogPage' => [
        'hero' => [
            'title' => 'Beauty News',
            'backgroundImage' => '/mockImages/blog-hero.jpg',
        ],
    ],

    'contactPage' => [
        'hero' => [
            'image' => '/mockImages/contact-hero.jpg',
            'title' => 'Stay Connected',
        ],
        'faq' => [
            'smallTitle' => 'Insights & Clarity',
            'title' => 'Your Beauty Journey Starts Here: Discover Answers to Questions',
            'backgroundImage' => '/mockImages/contact-faq-bg.jpg',
            'questions' => [
                [
                    'category' => 'Pricing',
                    'question' => 'How does the appointment process work?',
                    'answer' => 'Our appointment process is simple and convenient. You can book online through our website, call us directly, or send a message via WhatsApp. Once booked, you\'ll receive a confirmation with all the details including date, time, and service information. We recommend booking at least 24 hours in advance to secure your preferred time slot.',
                ],
                [
                    'category' => 'Process',
                    'question' => 'How does the appointment process work?',
                    'answer' => 'When you arrive for your appointment, our team will greet you and guide you through a brief consultation to understand your needs and preferences. We\'ll then proceed with your selected service in a comfortable, private setting. Each session is tailored to your specific requirements to ensure the best possible results.',
                ],
                [
                    'category' => 'Booking',
                    'question' => 'How does the appointment process work?',
                    'answer' => 'Booking with us is effortless. Simply visit our services page, select your desired treatment, choose an available time slot, and complete your booking. You\'ll receive instant confirmation via email and SMS. If you need to reschedule or cancel, you can do so up to 12 hours before your appointment without any charges.',
                ],
                [
                    'category' => 'Process',
                    'question' => 'How does the appointment process work?',
                    'answer' => 'We follow a meticulous process to ensure your satisfaction. After your initial consultation, we prepare all necessary equipment and products. During the service, our experienced professionals work with precision and care. Post-service, we provide aftercare instructions and recommend products to maintain your results at home.',
                ],
                [
                    'category' => 'Process',
                    'question' => 'How does the appointment process work?',
                    'answer' => 'Your first visit begins with a comprehensive consultation where we discuss your beauty goals and any concerns. We then create a personalized treatment plan and proceed with the service. Throughout the process, we maintain open communication to ensure your comfort. Follow-up appointments can be scheduled based on your treatment plan and desired outcomes.',
                ],
            ],
        ],
    ],

    'general' => [
        'footer' => [
            'images' => [
                [
                    'label' => 'Image 1',
                    'src' => '/mockImages/1.png',
                ],
            ],
            'info' => [
                'title' => 'Your beauty journey starts here with unmatched expertise and care!',
                'desc' => 'Discover our difference today and let us help you shine brighter than ever. Visit us to embrace beauty, confidence, and elegance!',
                'button' => 'Book Appointment',
            ],
            'backgroundImage' => '/mockImages/footer-bottom.jpg',
        ],
        'contact' => [
            'socials' => [
                [
                    'label' => 'Instagram',
                    'href' => 'https://instagram.com/rjbeauty',
                ],
                [
                    'label' => 'Facebook',
                    'href' => 'https://facebook.com/rjbeauty',
                ],
                [
                    'label' => 'WhatsApp',
                    'href' => 'https://wa.me/77890908765',
                ],
            ],
            'phoneNumber' => '+7 7890 908765',
            'email' => 'example@gmail.com',
            'address' => '225 East 57th Street, Building, Post Number, UAE 10022',
            'googleMapLink' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3608.123456789!2d55.123456!3d25.123456',
        ],
    ],
    'storePage' => [
        'hero' => [
            'image' => '/mockImages/product-hero.jpg',
            'title' => 'Our Beauty Products',
        ],
    ],
];
