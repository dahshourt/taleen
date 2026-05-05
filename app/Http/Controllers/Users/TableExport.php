<?php

namespace App\Http\Controllers\Users;

use App\Models\User; // Replace with your actual model
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TableExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        // Eager load the 'defualt_group' relationship and select necessary fields
        return User::with('defualt_group') // Corrected the relationship method name to 'defualt_group'
            ->select('id', 'name', 'user_name', 'email', 'active', 'default_group') // Select necessary fields from users
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'User Name',
            'Email',
            'Group',
            'Status',
        ];
    }

    /**
     * @param  \App\Models\User  $user
     */
    public function map($user): array
    {
        return [
            $user->id,
            $user->name,
            $user->user_name,
            $user->email,
            $user->defualt_group ? $user->defualt_group->title : 'No Group', // Use 'defualt_group' for group title
            $user->active ? 'Active' : 'Inactive', // Map active status to a human-readable string
        ];
    }
}
