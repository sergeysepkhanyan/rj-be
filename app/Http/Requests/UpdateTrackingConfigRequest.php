<?php

namespace App\Http\Requests;

class UpdateTrackingConfigRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'googleAnalyticsId' => 'google_analytics_id',
        'googleTagManagerId' => 'google_tag_manager_id',
        'facebookPixelId' => 'facebook_pixel_id',
        'snapchatPixelId' => 'snapchat_pixel_id',
        'customScripts' => 'custom_scripts',
    ];

    public function rules(): array
    {
        return [
            'googleAnalyticsId' => ['nullable', 'string', 'max:50'],
            'googleTagManagerId' => ['nullable', 'string', 'max:50'],
            'facebookPixelId' => ['nullable', 'string', 'max:50'],
            'snapchatPixelId' => ['nullable', 'string', 'max:100'],
            'customScripts' => ['nullable', 'array'],
            'customScripts.*.id' => ['nullable', 'string'],
            'customScripts.*.name' => ['required_with:customScripts', 'string', 'max:100'],
            'customScripts.*.code' => ['required_with:customScripts', 'string'],
            'customScripts.*.position' => ['required_with:customScripts', 'string', 'in:head,body_start,body_end'],
            'customScripts.*.enabled' => ['sometimes', 'boolean'],
            'customScripts.*._delete' => ['sometimes', 'boolean'],
        ];
    }
}
