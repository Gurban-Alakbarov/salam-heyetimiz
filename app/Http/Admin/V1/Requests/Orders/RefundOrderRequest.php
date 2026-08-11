<?php

namespace App\Http\Admin\V1\Requests\Orders;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Payments\DTOs\RefundRequestData;
use Illuminate\Foundation\Http\FormRequest;

/** Validates the adminRefundOrder body; gated on refunds.create (403 precedes body validation). */
class RefundOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof AdminUser && $actor->hasPermission(Permission::REFUNDS_CREATE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    public function toData(): RefundRequestData
    {
        $validated = $this->validated();

        return new RefundRequestData(
            amountMinor: (int) $validated['amount_minor'],
            reason: $validated['reason'],
        );
    }
}
