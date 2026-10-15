<?php

namespace App\Handlers\Ticket;

use App\Handlers\Concerns\QueuesNotifications;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\TicketWorkflowService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateTicketReplyHandler
{
    use QueuesNotifications;

    public function handle(User $author, Ticket $ticket, string $body): TicketReply
    {
        $reply = DB::transaction(function () use ($author, $ticket, $body): TicketReply {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $workflow = $this->workflow();

            if (! $workflow->acceptsReplies($locked->status)) {
                throw new ConflictHttpException('Replies are closed on resolved tickets.');
            }

            $reply = $locked->replies()->create([
                'user_id' => $author->id,
                'body' => $body,
            ]);

            $next = $author->id === $locked->customer_id
                ? $workflow->statusAfterCustomerReply($locked->status)
                : null;

            if ($next !== null) {
                $workflow->transition($locked, $next, $author);
            } else {
                $locked->update(['last_activity_at' => now()]);
            }

            $reply->setRelation('ticket', $locked);

            return $reply->load('user.roles');
        });

        $this->queueMail($this->notifications()->forReply($reply->ticket, $reply));

        return $reply;
    }

    private function workflow(): TicketWorkflowService
    {
        return app(TicketWorkflowService::class);
    }
}
