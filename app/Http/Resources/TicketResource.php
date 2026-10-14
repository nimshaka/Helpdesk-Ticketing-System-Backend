<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'description' => $this->description,
            'priority' => $this->priority->value,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ]),
            'agent' => $this->whenLoaded('activeAgent', function () {
                $agent = $this->activeAgent->first();

                return $agent === null ? null : [
                    'id' => $agent->id,
                    'name' => $agent->name,
                ];
            }),
            'replies' => TicketReplyResource::collection($this->whenLoaded('replies')),
            'status_history' => TicketStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'assignments' => $this->whenLoaded('agents', fn () => $this->agents->map(fn ($agent) => [
                'agent' => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                ],
                'assigned_by' => $agent->pivot->assigned_by,
                'assigned_at' => $agent->pivot->assigned_at,
                'unassigned_at' => $agent->pivot->unassigned_at,
            ])->all()),
            'last_activity_at' => $this->last_activity_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
