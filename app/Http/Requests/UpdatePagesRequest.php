<?php

namespace App\Http\Requests;

class UpdatePagesRequest extends BaseFormRequest
{
    protected array $fieldMap = [];

    public function rules(): array
    {
        $others = 'about,contact,blog,store,general';

        return [
            'homepage' => ["required_without_all:{$others}", 'array'],
            'about' => ['required_without_all:homepage,contact,blog,store,general', 'array'],
            'contact' => ['required_without_all:homepage,about,blog,store,general', 'array'],
            'blog' => ['required_without_all:homepage,about,contact,store,general', 'array'],
            'store' => ['required_without_all:homepage,about,contact,blog,general', 'array'],
            'general' => ['required_without_all:homepage,about,contact,blog,store', 'array'],
        ];
    }
}
