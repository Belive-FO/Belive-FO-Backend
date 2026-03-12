<?php

namespace App\Modules\Shared\Contracts;

interface UserServiceInterface
{
    public function findById(int $userId): ?object;
    
    public function findByLarkUserId(string $larkUserId): ?object;
}


