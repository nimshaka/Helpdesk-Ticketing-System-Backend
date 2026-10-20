<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TicketReplyService
{
    public function createTicketReplyService(User $author, Ticket $ticket, string $body): TicketReply
    {
        $reply = DB::transaction(function () use ($author, $ticket, $body): TicketReply {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $workflow = app(TicketWorkflowService::class);

            if (! $workflow->ticketAcceptsRepliesService($locked->status)) {
                throw new ConflictHttpException('Replies are closed on resolved tickets.');
            }

            $reply = $locked->replies()->create([
                'user_id' => $author->id,
                'body' => $body,
            ]);

            $next = $author->id === $locked->customer_id
                ? $workflow->ticketStatusAfterCustomerReplyService($locked->status)
                : null;

            if ($next !== null) {
                $workflow->ticketTransitionService($locked, $next, $author);
            } else {
                $locked->update(['last_activity_at' => now()]);
            }

            $reply->setRelation('ticket', $locked);

            return $reply->load('user.roles');
        });

        return $reply;
    }
}
