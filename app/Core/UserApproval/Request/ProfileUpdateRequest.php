<?php

namespace App\Core\UserApproval\Request;

use App\Core\User\Models\User;
use App\Modules\HR\Hr\Models\HRPerson;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Route uses {uuid} not {id}, so resolve the real PK for unique() exclusion.
        $hrperson=HRPerson::where('uuid', $this->route('uuid'))->firstOrFail();
        $userId = User::findOrFail($hrperson->user_id)->id;

        return [
            // ── Security ──
            'password'        => 'nullable|string|min:8',

            // ── Personal ──
            'first_name'      => 'required|string|max:255',
            'surname'         => 'required|string|max:255',
            'initial'         => 'nullable|string|max:10',
            'known_as'        => 'nullable|string|max:255',
            'title'           => 'nullable|string|max:20',
            'id_number'       => 'nullable|string|max:50',

            // ── Contact ──
            'cell_number'      => 'nullable|string|max:20',
            'phone_number'     => 'nullable|string|max:20',
            'email_person'     => 'nullable|email|max:255',

            // ── Adresse ──

            // ── Urgence ──

            // ── Tax ──

            // ── Banque ──

            // ── Medical ──

            // ── Provident ──

            // ── Travail ──
            'position_id'          => 'nullable|exists:hr_positions,id',
            'date_joined'          => 'nullable|date',
            'date_left'            => 'nullable|date',
            'role_id'              => 'nullable|exists:roles,id',
            'manager_id'           => 'nullable|exists:users,id',
            'second_manager_id'    => 'nullable|exists:users,id',

            // ── Conduite ──

            // ── Package ──

            // ── Autre ──

            // ── Compte (Admin) ──
            'role'   => 'nullable|string|exists:roles,name',
            'status' => 'nullable|integer|in:0,1',
            'email'  => 'nullable|email|max:255|unique:users,email,' . $userId,

            // ── Fichiers ──
            'profile_pic'        => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:10240',

            // ── Company Units ──
            'company_ids'   => 'nullable|array',
            'company_ids.*' => 'integer|exists:company_units,id',


        ];
    }

    public function prepareForValidation(): void
    {
        $nullableFields = [
            'date_joined',
            'date_left',
            'initial',
            'known_as',
            'title',
            'cell_number',
            'phone_number',
            'email_person',
            'position_id',
            'role_id',
            'manager_id',
            'second_manager_id',
            'role',
            'status',
            'password',
        ];

        $merged = [];
        foreach ($nullableFields as $field) {
            $val = $this->input($field);
            if ($val === 'null' || $val === '') {
                $merged[$field] = null;
            }
        }

        if (!empty($merged)) {
            $this->merge($merged);
        }
    }
}
