<?php

namespace App\Services\Admin;

use App\Http\Requests\Admin\ListUsersRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class UserIndexQuery
{
    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(ListUsersRequest $request): LengthAwarePaginator
    {
        $filters = $request->validated();
        $search = $filters['search'] ?? null;
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';
        $perPage = (int) ($filters['per_page'] ?? 15);

        return User::query()
            ->subscribers()
            ->when($search, function (Builder $query, string $search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->orderBy($sort, $direction)
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }
}
