<?php

namespace App\Http\Resources;

use App\Models\TicketStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketStatusHistory
 */
class TicketStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from' => $this->from_status === null ? null : [
                'value' => $this->from_status->value,
                'label' => $this->from_status->label(),
            ],
            'to' => [
                'value' => $this->to_status->value,
                'label' => $this->to_status->label(),
            ],
            'changed_by' => $this->whenLoaded('changedBy', fn () => [
                'id' => $this->changedBy->id,
                'name' => $this->changedBy->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
