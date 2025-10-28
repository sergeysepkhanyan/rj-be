<?php

namespace App\Http\Controllers\API\Content;

use Illuminate\Http\JsonResponse;

;

class PageContentController extends BaseContentController
{
    public function home(): JsonResponse
    {
        return $this->getPageContent('home');
    }

    public function about(): JsonResponse
    {
        return $this->getPageContent('about');
    }

    public function contact(): JsonResponse
    {
        return $this->getPageContent('contact');
    }

    public function blog(): JsonResponse
    {
        return $this->getPageContent('blog');
    }

    public function store(): JsonResponse
    {
        return $this->getPageContent('store');
    }

    public function general(): JsonResponse
    {
        return $this->getPageContent('general');
    }
}

