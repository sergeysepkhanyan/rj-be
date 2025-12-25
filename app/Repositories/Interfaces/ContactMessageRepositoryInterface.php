<?php
namespace App\Repositories\Interfaces;

use App\Models\ContactMessage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ContactMessageRepositoryInterface
{
    public function create(array $data): ContactMessage;

    public function markEmailed(ContactMessage $message): ContactMessage;

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator;
}

